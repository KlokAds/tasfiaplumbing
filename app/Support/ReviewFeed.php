<?php

namespace App\Support;

use App\Models\FeedBackContent;
use App\Models\GoogleReview;
use App\Models\SiteSetting;

/**
 * One list of reviews for the website: Google reviews first (they carry the most trust),
 * then your own. Pages receive the same shape whatever the source.
 *
 * Google source, best first:
 *  1. Business Profile (Admin → Insights → Google connections): every review, synced daily.
 *  2. Places API key (Admin → Reviews): Google's 5 "most relevant" reviews.
 */
class ReviewFeed
{
    public static function build(?int $limit = null): array
    {
        $min = (int) (SiteSetting::get('reviews.google_min_rating') ?: 4);
        $profile = self::businessProfile($min);
        $google = $profile ?? GoogleReviews::get();

        $own = [];
        if (SiteSetting::get('reviews.show_own', '1')) {
            $own = FeedBackContent::visible()
                ->orderByDesc('review_date')->latest()
                ->get()
                ->map(fn (FeedBackContent $r) => [
                    'source' => 'site',
                    'name' => $r->name,
                    'photo' => $r->img ? '/' . ltrim($r->img, '/') : null,
                    'rating' => $r->rating ?: 5,
                    'text' => trim(strip_tags((string) $r->desc)),
                    'when' => $r->review_date?->format('M Y'),
                    'location' => $r->location,
                    'job' => $r->job,
                    'url' => null,
                    'author_url' => null,
                ])->all();
        }

        $items = array_merge($google['reviews'], $own);

        return [
            'google' => $google['ok'] ? [
                'rating' => $google['rating'],
                'total' => $google['total'],
                'url' => $google['url'],
                'write_url' => $google['write_url'],
            ] : null,
            'items' => $limit ? array_slice($items, 0, $limit) : $items,
            'count' => count($items),
        ];
    }

    /** Reviews synced from the Business Profile, or null when that is not set up. */
    private static function businessProfile(int $min): ?array
    {
        if (!SiteSetting::get('google.gbp_location')) {
            return null;
        }
        try {
            $rows = GoogleReview::where('is_hidden', false)->where('rating', '>=', $min)
                ->whereNotNull('comment')->where('comment', '!=', '')
                ->orderByDesc('reviewed_at')->get();
        } catch (\Throwable) {
            return null;
        }
        if ($rows->isEmpty() && !SiteSetting::get('google.gbp_total')) {
            return null;
        }

        $placeId = SiteSetting::get('google.gbp_place_id');
        $maps = SiteSetting::get('google.gbp_maps_uri') ?: ($placeId ? 'https://www.google.com/maps/place/?q=place_id:' . $placeId : null);

        return [
            'ok' => true,
            'rating' => (float) SiteSetting::get('google.gbp_rating') ?: null,
            'total' => (int) SiteSetting::get('google.gbp_total') ?: $rows->count(),
            'url' => $maps,
            'write_url' => SiteSetting::get('google.gbp_review_uri') ?: ($placeId ? 'https://search.google.com/local/writereview?placeid=' . $placeId : $maps),
            'reviews' => $rows->map(fn (GoogleReview $r) => [
                'source' => 'google',
                'name' => $r->author,
                'author_url' => null,
                'photo' => $r->photo,
                'rating' => $r->rating,
                'text' => $r->comment,
                'reply' => $r->reply,
                'when' => $r->reviewed_at?->diffForHumans(),
                'published_at' => $r->reviewed_at?->toIso8601String(),
                'url' => $maps,
            ])->all(),
        ];
    }
}
