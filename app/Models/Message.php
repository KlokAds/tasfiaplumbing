<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Message extends Model
{
    use HasFactory;
    protected $guarded =[];

    protected $casts = ['is_spam' => 'boolean', 'status_at' => 'datetime', 'reminded_at' => 'datetime'];

    /** Enquiries that are not in the Spam folder (App\Support\SpamCheck). */
    public function scopeNotSpam($query)
    {
        return $query->where('is_spam', false);
    }
}
