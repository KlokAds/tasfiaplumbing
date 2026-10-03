<?php

namespace App\Support;

use App\Models\SiteSetting;
use App\Models\User;
use App\Notifications\ApiKeyChanged;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;

/**
 * Every API key, token and password of the website in one place (Admin → System → Settings → API keys).
 * Each is stored encrypted in site settings under its existing name, is never shown again after saving
 * (only the last 4 characters), can be changed only by a Super Admin, and every change is logged and
 * emailed to the hidden maintenance account (the owner's mailbox).
 */
class ApiKeys
{
    /** name => setting, what it is for, where to get it, the page that uses it. */
    public const KEYS = [
        'brave' => [
            'setting' => 'source_check.key',
            'label' => 'Brave Search API key',
            'used_for' => 'Source check: finds article text copied from other websites.',
            'get' => 'brave.com/search/api → sign in → API keys.',
            'page' => ['Articles', '/admin/blogs?tab=review'],
        ],
        'google_places' => [
            'setting' => 'reviews.google_api_key',
            'label' => 'Google Places API key',
            'used_for' => 'Google reviews and star rating on the website.',
            'get' => 'Google Cloud console → APIs & Services → Credentials (Places API (New) enabled).',
            'page' => ['Reviews', '/admin/reviews'],
        ],
        'google_secret' => [
            'setting' => 'google.client_secret',
            'label' => 'Google OAuth client secret',
            'used_for' => 'Search Console, Analytics and Business Profile data in Insights.',
            'get' => 'Google Cloud console → Credentials → your OAuth client. The Client ID stays in Insights → Google.',
            'page' => ['Insights → Google', '/admin/insights/google'],
        ],
        'github' => [
            'setting' => 'deploy.token',
            'label' => 'GitHub access token',
            'used_for' => 'System → Update: downloads the code from a private repository.',
            'get' => 'GitHub → Settings → Developer settings → Fine-grained tokens: only this repository, Contents read-only.',
            'page' => ['System → Update', '/admin/system/update'],
        ],
        'smtp' => [
            'setting' => 'mail.password',
            'label' => 'Email (SMTP) password',
            'used_for' => 'Sending enquiry alerts and admin emails.',
            'get' => 'The password of the mailbox set in System → Settings → Email.',
            'page' => ['Email settings', '/admin/system/settings?tab=mail'],
        ],
    ];

    public static function value(string $name): ?string
    {
        $stored = SiteSetting::stored(self::KEYS[$name]['setting']);
        if (!$stored) {
            return null;
        }
        try {
            return Crypt::decryptString($stored);
        } catch (\Throwable) {
            return null; // APP_KEY changed: the key has to be saved again
        }
    }

    /** The list for the API keys tab: never the keys themselves, only whether they are saved and the last 4. */
    public static function list(): array
    {
        return collect(self::KEYS)->map(function ($k, $name) {
            $value = self::value($name);
            $fromServer = !$value && $name === 'google_places' && env('GOOGLE_PLACES_API_KEY');

            return [
                'name' => $name,
                'label' => $k['label'],
                'used_for' => $k['used_for'],
                'get' => $k['get'],
                'page' => ['label' => $k['page'][0], 'url' => $k['page'][1]],
                'saved' => (bool) $value,
                'last4' => $value ? substr($value, -4) : null,
                'from_server' => (bool) $fromServer,
                'updated_at' => $value ? SiteSetting::where('key', $k['setting'])->value('updated_at')?->toIso8601String() : null,
            ];
        })->values()->all();
    }

    /** Short status for the pages that use a key ("Saved ••••1234" / "Not set"). */
    public static function status(string $name): array
    {
        $value = self::value($name);

        return ['saved' => (bool) $value, 'last4' => $value ? substr($value, -4) : null, 'manage_url' => '/admin/system/settings?tab=keys'];
    }

    /** Checks a new key with its service before it is saved. Returns the problem, or null when it works. */
    public static function test(string $name, string $value): ?string
    {
        return match ($name) {
            'brave' => SourceCheck::testKey($value),
            'google_places' => preg_match('/^[A-Za-z0-9_\-]{20,}$/', $value) ? null : 'That does not look like a Google API key.',
            default => null,
        };
    }

    public static function save(string $name, ?string $value): void
    {
        SiteSetting::putMany([self::KEYS[$name]['setting'] => filled($value) ? Crypt::encryptString(trim($value)) : '']);
        if ($name === 'google_places') {
            GoogleReviews::flush();
        }
    }

    /** Logged, and emailed to the hidden maintenance account. */
    public static function alert(string $name, string $action, User $by, ?string $ip, ?string $last4): void
    {
        $label = self::KEYS[$name]['label'];
        Log::warning("{$label} {$action}", ['by' => $by->email, 'ip' => $ip]);
        $to = User::where('is_hidden', true)->where('is_active', true)->role(config('admin.super_role'))->get()->filter(fn ($u) => filled($u->email));
        foreach ($to as $owner) {
            try {
                $owner->notify(new ApiKeyChanged($label, $action, $by->name, $ip, $last4));
            } catch (\Throwable $e) {
                Log::warning('API key alert email failed', ['user' => $owner->id, 'error' => $e->getMessage()]);
            }
        }
    }
}
