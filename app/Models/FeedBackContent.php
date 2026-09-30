<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/** Reviews added by hand in Admin > Reviews (Google reviews are fetched live, see GoogleReviews). */
class FeedBackContent extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'desc', 'img', 'rating', 'location', 'job', 'review_date', 'is_active'];

    protected $casts = ['rating' => 'integer', 'is_active' => 'boolean', 'review_date' => 'date'];

    public function scopeVisible(Builder $q): Builder
    {
        return $q->where('is_active', true);
    }
}
