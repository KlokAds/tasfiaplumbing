<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class SiteSetting extends Model
{
    public const CACHE_KEY = 'site_settings.all';

    protected $fillable = ['key', 'value'];

    protected static function booted(): void
    {
        static::saved(fn () => Cache::forget(self::CACHE_KEY));
        static::deleted(fn () => Cache::forget(self::CACHE_KEY));
    }

    /** Stored values merged over the defaults declared in config/seo.php. */
    public static function values(): array
    {
        $stored = [];
        try {
            $stored = Cache::rememberForever(self::CACHE_KEY, fn () => static::query()->pluck('value', 'key')->all());
        } catch (\Throwable $e) {
            // Table missing (fresh install before migrate): fall back to defaults.
        }

        $values = [];
        foreach (config('seo.settings', []) as $group) {
            foreach ($group['fields'] as $key => $field) {
                $values[$key] = array_key_exists($key, $stored) ? $stored[$key] : ($field['default'] ?? null);
            }
        }

        return $values;
    }

    /** A stored value that has no field in config/seo.php (e.g. articles.default_author). */
    public static function stored(string $key, mixed $default = null): mixed
    {
        try {
            $value = Cache::rememberForever(self::CACHE_KEY, fn () => static::query()->pluck('value', 'key')->all())[$key] ?? null;
        } catch (\Throwable $e) {
            $value = null;
        }

        return ($value === null || $value === '') ? $default : $value;
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        $value = static::values()[$key] ?? null;

        return ($value === null || $value === '') ? $default : $value;
    }

    public static function putMany(array $values): void
    {
        foreach ($values as $key => $value) {
            static::updateOrCreate(['key' => $key], ['value' => is_bool($value) ? ($value ? '1' : '0') : $value]);
        }
        Cache::forget(self::CACHE_KEY);
        Cache::forget('admin.checklist.must');
    }
}
