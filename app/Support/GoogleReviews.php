<?php

namespace App\Support;

use App\Models\SiteSetting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Reviews from the Google Business Profile via the Places API (New).
 *
 * - Google returns at most 5 reviews per place (its "most relevant" ones), plus the
 *   overall star rating and total review count.
 * - Results are cached for 12 hours, so the API is called about twice a day (well inside
 *   the free monthly credit). Failures are cached for 30 minutes so a bad key never
 *   slows the site down.
 * - Google's terms require showing the reviewer's name and linking to Google, which the
 *   frontend does. Reviews are not copied into our database.
 */
class GoogleReviews
{
    public const CACHE_KEY = 'google_reviews.v1';

    public static function enabled(): bool
    {
        return (bool) SiteSetting::get('reviews.google_enabled') && self::placeId() && self::apiKey();
    }

    public static function placeId(): ?string
    {
        return SiteSetting::get('reviews.google_place_id') ?: null;
    }

    public static function apiKey(): ?string
    {
        $stored = SiteSetting::get('reviews.google_api_key');
        if (!$stored) {
            return env('GOOGLE_PLACES_API_KEY') ?: null;
        }
        try {
            return Crypt::decryptString($stored);
        } catch (\Throwable) {
            return null;
        }
    }

    public static function storeApiKey(string $key): void
    {
        SiteSetting::putMany(['reviews.google_api_key' => Crypt::encryptString(trim($key))]);
        self::flush();
    }

    public static function flush(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * The cached result used by the website. Never throws.
     *
     * @return array{ok: bool, error: ?string, rating: ?float, total: ?int, url: ?string, write_url: ?string, reviews: array, fetched_at: string}
     */
    public static function get(): array
    {
        if (!self::enabled()) {
            return self::empty('Google reviews are switched off or not set up.');
        }

        $cached = Cache::get(self::CACHE_KEY);
        if ($cached) {
            return $cached;
        }

        $result = self::fetch();
        Cache::put(self::CACHE_KEY, $result, $result['ok'] ? now()->addHours(12) : now()->addMinutes(30));

        return $result;
    }

    /** Always calls Google (admin "Refresh now" and the cache fill). */
    public static function fetch(): array
    {
        $placeId = self::placeId();
        $key = self::apiKey();
        if (!$placeId || !$key) {
            return self::empty('Add the Place ID and API key first.');
        }

        try {
            $response = Http::timeout(8)
                ->withHeaders([
                    'X-Goog-Api-Key' => $key,
                    'X-Goog-FieldMask' => 'displayName,rating,userRatingCount,reviews,googleMapsUri',
                ])
                ->get('https://places.googleapis.com/v1/places/' . rawurlencode($placeId), ['languageCode' => 'en']);
        } catch (\Throwable $e) {
            Log::warning('Google reviews request failed', ['error' => $e->getMessage()]);

            return self::empty('Could not reach Google. Try again later.');
        }

        if (!$response->successful()) {
            $message = $response->json('error.message') ?: 'Google returned HTTP ' . $response->status() . '.';
            Log::warning('Google reviews error', ['status' => $response->status(), 'message' => $message]);

            return self::empty(self::explain($response->status(), $message));
        }

        $min = (int) (SiteSetting::get('reviews.google_min_rating') ?: 4);
        $reviews = collect($response->json('reviews', []))
            ->map(fn ($r) => [
                'source' => 'google',
                'name' => $r['authorAttribution']['displayName'] ?? 'Google user',
                'author_url' => $r['authorAttribution']['uri'] ?? null,
                'photo' => $r['authorAttribution']['photoUri'] ?? null,
                'rating' => (int) ($r['rating'] ?? 0),
                'text' => trim($r['originalText']['text'] ?? $r['text']['text'] ?? ''),
                'when' => $r['relativePublishTimeDescription'] ?? null,
                'published_at' => $r['publishTime'] ?? null,
                'url' => $r['googleMapsUri'] ?? null,
            ])
            ->filter(fn ($r) => $r['rating'] >= $min && $r['text'] !== '')
            ->values()
            ->all();

        return [
            'ok' => true,
            'error' => null,
            'name' => $response->json('displayName.text'),
            'rating' => $response->json('rating') ? round((float) $response->json('rating'), 1) : null,
            'total' => $response->json('userRatingCount'),
            'url' => $response->json('googleMapsUri'),
            'write_url' => 'https://search.google.com/local/writereview?placeid=' . rawurlencode($placeId),
            'reviews' => $reviews,
            'fetched_at' => now()->toIso8601String(),
        ];
    }

    private static function explain(int $status, string $message): string
    {
        return match (true) {
            $status === 403 && str_contains($message, 'not been used') => 'Places API (New) is not enabled for this key. Enable it in Google Cloud Console.',
            $status === 403 => 'The API key was refused: ' . $message,
            $status === 400 || $status === 404 => 'Place ID not found. Copy it again from the Place ID Finder.',
            default => $message,
        };
    }

    private static function empty(string $error): array
    {
        return ['ok' => false, 'error' => $error, 'name' => null, 'rating' => null, 'total' => null, 'url' => null, 'write_url' => null, 'reviews' => [], 'fetched_at' => now()->toIso8601String()];
    }
}
