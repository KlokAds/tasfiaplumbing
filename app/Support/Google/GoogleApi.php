<?php

namespace App\Support\Google;

use App\Models\SiteSetting;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;

/**
 * One Google sign-in (OAuth) for everything: Business Profile reviews, Search Console and
 * Analytics. The owner creates a free OAuth client in Google Cloud, pastes its ID/secret in
 * admin and clicks "Connect Google". Only a refresh token is kept (encrypted).
 */
class GoogleApi
{
    public const SCOPES = [
        'openid',
        'email',
        'https://www.googleapis.com/auth/business.manage',
        'https://www.googleapis.com/auth/webmasters.readonly',
        'https://www.googleapis.com/auth/analytics.readonly',
    ];

    private const TOKEN_CACHE = 'google.access_token';

    public static function redirectUri(): string
    {
        return url('/admin/insights/google/callback');
    }

    public static function clientId(): ?string
    {
        return SiteSetting::get('google.client_id') ?: null;
    }

    public static function clientSecret(): ?string
    {
        return self::decrypt(SiteSetting::get('google.client_secret'));
    }

    public static function hasClient(): bool
    {
        return self::clientId() && self::clientSecret();
    }

    public static function connected(): bool
    {
        return self::hasClient() && filled(SiteSetting::get('google.refresh_token'));
    }

    public static function authUrl(string $state): string
    {
        return 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query([
            'client_id' => self::clientId(),
            'redirect_uri' => self::redirectUri(),
            'response_type' => 'code',
            'scope' => implode(' ', self::SCOPES),
            'access_type' => 'offline',
            'prompt' => 'consent',          // always returns a refresh token
            'include_granted_scopes' => 'true',
            'state' => $state,
        ]);
    }

    /** Swap the one-time code for tokens and remember who connected. */
    public static function exchange(string $code): void
    {
        $res = Http::asForm()->timeout(15)->post('https://oauth2.googleapis.com/token', [
            'code' => $code,
            'client_id' => self::clientId(),
            'client_secret' => self::clientSecret(),
            'redirect_uri' => self::redirectUri(),
            'grant_type' => 'authorization_code',
        ]);
        if (!$res->successful() || !$res->json('refresh_token')) {
            throw new \RuntimeException($res->json('error_description') ?: 'Google did not return a refresh token. Remove the app from your Google account permissions and try again.');
        }

        Cache::put(self::TOKEN_CACHE, $res->json('access_token'), now()->addSeconds(max(60, (int) $res->json('expires_in', 3600) - 120)));
        $email = null;
        if ($idToken = $res->json('id_token')) {
            $payload = json_decode(base64_decode(strtr(explode('.', $idToken)[1] ?? '', '-_', '+/')), true);
            $email = $payload['email'] ?? null;
        }

        SiteSetting::putMany([
            'google.refresh_token' => Crypt::encryptString($res->json('refresh_token')),
            'google.connected_email' => $email ?: '',
            'google.last_error' => '',
        ]);
    }

    public static function disconnect(): void
    {
        $refresh = self::decrypt(SiteSetting::get('google.refresh_token'));
        if ($refresh) {
            rescue(fn () => Http::asForm()->timeout(8)->post('https://oauth2.googleapis.com/revoke', ['token' => $refresh]), null, false);
        }
        Cache::forget(self::TOKEN_CACHE);
        SiteSetting::putMany(['google.refresh_token' => '', 'google.connected_email' => '']);
        self::flushReports();
    }

    public static function accessToken(): string
    {
        $cached = Cache::get(self::TOKEN_CACHE);
        if ($cached) {
            return $cached;
        }
        $refresh = self::decrypt(SiteSetting::get('google.refresh_token'));
        if (!$refresh || !self::hasClient()) {
            throw new GoogleException('Google is not connected.');
        }

        $res = Http::asForm()->timeout(15)->post('https://oauth2.googleapis.com/token', [
            'client_id' => self::clientId(),
            'client_secret' => self::clientSecret(),
            'refresh_token' => $refresh,
            'grant_type' => 'refresh_token',
        ]);
        if (!$res->successful()) {
            $error = $res->json('error') === 'invalid_grant'
                ? 'Google access was removed or expired. Click "Connect Google" again.'
                : ($res->json('error_description') ?: 'Could not refresh the Google login.');
            SiteSetting::putMany(['google.last_error' => $error]);
            throw new GoogleException($error);
        }

        $token = $res->json('access_token');
        Cache::put(self::TOKEN_CACHE, $token, now()->addSeconds(max(60, (int) $res->json('expires_in', 3600) - 120)));

        return $token;
    }

    public static function get(string $url, array $query = []): array
    {
        return self::handle(Http::withToken(self::accessToken())->timeout(20)->get($url, $query));
    }

    public static function post(string $url, array $body = []): array
    {
        return self::handle(Http::withToken(self::accessToken())->timeout(25)->post($url, $body));
    }

    private static function handle(Response $res): array
    {
        if ($res->successful()) {
            return $res->json() ?? [];
        }
        $message = $res->json('error.message') ?: 'Google returned HTTP ' . $res->status();
        if ($res->status() === 403 && (str_contains($message, 'has not been used') || str_contains($message, 'is disabled'))) {
            $message = 'This Google API is not switched on in your Google Cloud project. ' . $message;
        }
        throw new GoogleException($message, $res->status());
    }

    /** Report caches (Search Console, Analytics) — cleared by "Refresh" and on disconnect. */
    public static function flushReports(): void
    {
        foreach (['google.gsc.report', 'google.ga4.report', 'google.dashboard'] as $key) {
            Cache::forget($key);
        }
    }

    private static function decrypt(?string $value): ?string
    {
        if (!$value) {
            return null;
        }
        try {
            return Crypt::decryptString($value);
        } catch (\Throwable) {
            return null;
        }
    }
}
