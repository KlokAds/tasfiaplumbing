<?php

namespace App\Support\Google;

use App\Models\GoogleReview;
use App\Models\SiteSetting;
use App\Support\GoogleReviews;

/**
 * Google Business Profile: every review of the business (not just the 5 the Places API gives),
 * with replies. Synced once a day by the scheduler and on demand from admin.
 */
class BusinessProfile
{
    /** @return array<int, array{name: string, title: string, address: ?string}> "accounts/1/locations/2" */
    public static function locations(): array
    {
        $out = [];
        $accounts = GoogleApi::get('https://mybusinessaccountmanagement.googleapis.com/v1/accounts')['accounts'] ?? [];
        foreach ($accounts as $account) {
            $data = GoogleApi::get("https://mybusinessbusinessinformation.googleapis.com/v1/{$account['name']}/locations", [
                'readMask' => 'name,title,storefrontAddress,metadata',
                'pageSize' => 100,
            ]);
            foreach ($data['locations'] ?? [] as $loc) {
                $out[] = [
                    'name' => $account['name'] . '/' . $loc['name'],
                    'title' => $loc['title'] ?? $loc['name'],
                    'address' => implode(', ', array_filter($loc['storefrontAddress']['addressLines'] ?? [])) ?: null,
                    'place_id' => $loc['metadata']['placeId'] ?? null,
                    'maps_uri' => $loc['metadata']['mapsUri'] ?? null,
                    'review_uri' => $loc['metadata']['newReviewUri'] ?? null,
                ];
            }
        }

        return $out;
    }

    public static function selected(): ?string
    {
        return SiteSetting::get('google.gbp_location') ?: null;
    }

    /** Pull every review; reviews deleted on Google are removed here too. Returns the count. */
    public static function sync(): int
    {
        $location = self::selected();
        if (!$location) {
            throw new GoogleException('Choose your business location first.');
        }

        $seen = [];
        $token = null;
        $summary = [];
        $pages = 0;
        do {
            $data = GoogleApi::get("https://mybusiness.googleapis.com/v4/{$location}/reviews", array_filter([
                'pageSize' => 50,
                'orderBy' => 'updateTime desc',
                'pageToken' => $token,
            ]));
            $summary = $summary ?: ['rating' => $data['averageRating'] ?? null, 'total' => $data['totalReviewCount'] ?? null];

            foreach ($data['reviews'] ?? [] as $r) {
                $id = $r['reviewId'] ?? null;
                if (!$id) {
                    continue;
                }
                $seen[] = $id;
                $existing = GoogleReview::firstOrNew(['review_id' => $id]);
                $existing->fill([
                    'author' => ($r['reviewer']['isAnonymous'] ?? false) ? 'Google user' : ($r['reviewer']['displayName'] ?? 'Google user'),
                    'photo' => $r['reviewer']['profilePhotoUrl'] ?? null,
                    'rating' => self::stars($r['starRating'] ?? ''),
                    'comment' => self::clean($r['comment'] ?? ''),
                    'reply' => self::clean($r['reviewReply']['comment'] ?? '') ?: null,
                    'reviewed_at' => $r['createTime'] ?? null,
                ])->save();
            }
            $token = $data['nextPageToken'] ?? null;
        } while ($token && ++$pages < 40);

        if (!$token) {
            GoogleReview::whereNotIn('review_id', $seen ?: ['-'])->delete();
        }

        SiteSetting::putMany([
            'google.gbp_rating' => $summary['rating'] !== null ? (string) round((float) $summary['rating'], 1) : '',
            'google.gbp_total' => (string) ($summary['total'] ?? count($seen)),
            'google.gbp_synced_at' => now()->toIso8601String(),
            'google.last_error' => '',
        ]);
        GoogleReviews::flush();

        return count($seen);
    }

    /**
     * Non-English reviews arrive as "original (Translated by Google) english" or
     * "(Translated by Google) english (Original) original". Keep one version: the English one.
     */
    private static function clean(string $text): string
    {
        if (preg_match('/^\s*\(Translated by Google\)\s*(.*?)\s*\(Original\)/s', $text, $m)) {
            return trim($m[1]);
        }
        if (preg_match('/\(Translated by Google\)\s*(.+)$/s', $text, $m)) {
            return trim($m[1]);
        }

        return trim($text);
    }

    private static function stars(string $enum): int
    {
        return ['ONE' => 1, 'TWO' => 2, 'THREE' => 3, 'FOUR' => 4, 'FIVE' => 5][$enum] ?? 0;
    }
}
