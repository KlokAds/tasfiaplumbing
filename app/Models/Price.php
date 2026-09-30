<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Price extends Model
{
    public const STALE_AFTER_DAYS = 90;

    protected $fillable = [
        'service_id', 'item', 'price_from', 'price_to', 'unit', 'currency', 'gst_note',
        'is_featured', 'is_active', 'sort_order', 'last_reviewed_at',
    ];

    protected $casts = [
        'price_from' => 'integer',
        'price_to' => 'integer',
        'is_featured' => 'boolean',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
        'last_reviewed_at' => 'datetime',
    ];

    protected $appends = ['label', 'is_stale'];

    public function service()
    {
        return $this->belongsTo(ServiceDetail::class, 'service_id');
    }

    /** "S$80 – S$250 / unit" — the exact wording shown on pages and quoted by AI engines. */
    public function getLabelAttribute(): string
    {
        $label = 'S$' . number_format($this->price_from);
        if ($this->price_to && $this->price_to > $this->price_from) {
            $label .= ' – S$' . number_format($this->price_to);
        } else {
            $label = 'From ' . $label;
        }

        return $this->unit ? $label . ' / ' . $this->unit : $label;
    }

    public function getIsStaleAttribute(): bool
    {
        return !$this->last_reviewed_at || $this->last_reviewed_at->lt(now()->subDays(self::STALE_AFTER_DAYS));
    }
}
