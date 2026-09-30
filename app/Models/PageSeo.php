<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class PageSeo extends Model
{
    protected $table = 'page_seo';

    protected $fillable = ['key', 'meta_title', 'meta_desc', 'focus_keyword', 'og_image', 'canonical', 'noindex', 'updated_by'];

    protected $casts = ['noindex' => 'boolean'];

    protected static function booted(): void
    {
        static::saved(fn () => Cache::forget('page_seo.all'));
    }

    public static function forKey(string $key): ?self
    {
        try {
            return Cache::rememberForever('page_seo.all', fn () => static::all()->keyBy('key'))->get($key);
        } catch (\Throwable $e) {
            return null;
        }
    }
}
