<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Draft extends Model
{
    public const TYPES = ['service', 'article'];

    protected $fillable = ['user_id', 'type', 'record_id', 'payload'];

    protected $casts = ['payload' => 'array', 'record_id' => 'integer'];

    public static function discard(int $userId, string $type, int $recordId): void
    {
        static::where(['user_id' => $userId, 'type' => $type, 'record_id' => $recordId])->delete();
    }
}
