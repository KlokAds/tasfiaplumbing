<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Redirect extends Model
{
    public const CACHE_KEY = 'redirects.active_map';

    protected $fillable = ['from_path', 'to_path', 'code', 'source', 'hit_count', 'last_hit_at', 'is_active', 'notes'];

    protected $casts = [
        'code' => 'integer',
        'hit_count' => 'integer',
        'is_active' => 'boolean',
        'last_hit_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::saved(function (self $redirect) {
            Cache::forget(self::CACHE_KEY);
            NotFoundLog::where('path', $redirect->from_path)->update(['is_resolved' => true]);
        });
        static::deleted(fn () => Cache::forget(self::CACHE_KEY));
    }

    /**
     * Normalise to "/path" (no trailing slash) plus optional "?query".
     * Absolute URLs pointing at another domain are kept as-is (cross-domain merges).
     */
    public static function normalize(?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }
        $value = trim($value);
        $parts = parse_url($value);
        if ($parts === false) {
            return null;
        }

        $host = $parts['host'] ?? null;
        if ($host && strcasecmp($host, parse_url(config('app.url'), PHP_URL_HOST) ?? '') !== 0) {
            return $value;
        }

        $path = '/' . trim($parts['path'] ?? '', '/');
        return isset($parts['query']) && $parts['query'] !== '' ? $path . '?' . $parts['query'] : $path;
    }

    public static function remember(string $from, string $to, int $code = 301, string $source = 'auto'): void
    {
        $from = self::normalize($from);
        $to = self::normalize($to);
        if (!$from || !$to || $from === $to) {
            return;
        }

        // The page now lives at $to again, so a redirect away from $to would loop.
        static::where('from_path', $to)->delete();
        // Flatten chains: anything that pointed at $from now goes straight to $to.
        static::where('to_path', $from)->update(['to_path' => $to]);
        Cache::forget(self::CACHE_KEY);

        static::updateOrCreate(
            ['from_path' => $from],
            ['to_path' => $to, 'code' => $code, 'source' => $source, 'is_active' => true],
        );
    }

    public static function rememberGone(string $path): void
    {
        $path = self::normalize($path);
        if ($path) {
            static::firstOrCreate(
                ['from_path' => $path],
                ['to_path' => null, 'code' => 410, 'source' => 'auto', 'notes' => 'Page deleted. Change to a 301 if a relevant replacement page exists.'],
            );
        }
    }

    /** @return array<string, array{0:int,1:?string,2:int}> */
    public static function activeMap(): array
    {
        return Cache::rememberForever(self::CACHE_KEY, fn () => static::query()
            ->where('is_active', true)
            ->get(['id', 'from_path', 'to_path', 'code'])
            ->mapWithKeys(fn ($r) => [$r->from_path => [$r->id, $r->to_path, $r->code]])
            ->all());
    }
}
