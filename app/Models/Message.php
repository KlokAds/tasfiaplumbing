<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Message extends Model
{
    use HasFactory;
    protected $guarded =[];

    protected $casts = ['is_spam' => 'boolean', 'status_at' => 'datetime', 'reminded_at' => 'datetime', 'wants_visit' => 'boolean', 'visit_date' => 'date', 'photos' => 'array'];

    protected $appends = ['visit_label', 'photo_urls'];

    public const VISIT_TIMES = ['any' => 'any time (8am – 10pm)', 'morning' => 'morning (8am – 12pm)', 'afternoon' => 'afternoon (12pm – 5pm)', 'evening' => 'evening (5pm – 10pm)'];

    /** "Free site visit: Tue 14 Oct, morning (8am – 12pm)" or null. */
    public function getVisitLabelAttribute(): ?string
    {
        if (!$this->wants_visit) {
            return null;
        }

        return trim(($this->visit_date ? $this->visit_date->format('D j M') . ', ' : '') . (self::VISIT_TIMES[$this->visit_time] ?? self::VISIT_TIMES['any']));
    }

    /** The customer's photos, through the admin (private files). */
    public function getPhotoUrlsAttribute(): array
    {
        return collect($this->photos ?? [])->keys()->map(fn ($i) => url("/admin/messages/{$this->id}/photos/{$i}"))->all();
    }

    /** Enquiries that are not in the Spam folder (App\Support\SpamCheck). */
    public function scopeNotSpam($query)
    {
        return $query->where('is_spam', false);
    }
}
