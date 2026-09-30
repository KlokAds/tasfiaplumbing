<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GoogleReview extends Model
{
    protected $fillable = ['review_id', 'author', 'photo', 'rating', 'comment', 'reply', 'is_hidden', 'reviewed_at'];

    protected $casts = ['reviewed_at' => 'datetime', 'is_hidden' => 'boolean', 'rating' => 'integer'];
}
