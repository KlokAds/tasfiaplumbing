<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class NotFoundLog extends Model
{
    protected $fillable = ['path', 'hits', 'last_referer', 'last_seen_at', 'is_resolved'];

    protected $casts = [
        'hits' => 'integer',
        'is_resolved' => 'boolean',
        'last_seen_at' => 'datetime',
    ];

    public static function record(string $path, ?string $referer): void
    {
        // Bots probing for .env, WordPress, vendor files…: a 404 is the right answer, not worth listing.
        if (\App\Support\NotFoundRules::isNoise($path)) {
            return;
        }
        $now = now();
        static::query()->upsert(
            [[
                'path' => Str::limit($path, 500, ''),
                'hits' => 1,
                'last_referer' => $referer ? Str::limit($referer, 500, '') : null,
                'last_seen_at' => $now,
                'is_resolved' => false,
                'created_at' => $now,
                'updated_at' => $now,
            ]],
            ['path'],
            [
                'hits' => DB::raw('hits + 1'),
                'last_referer' => $referer ? Str::limit($referer, 500, '') : DB::raw('last_referer'),
                'last_seen_at' => $now,
                'updated_at' => $now,
            ],
        );
    }
}
