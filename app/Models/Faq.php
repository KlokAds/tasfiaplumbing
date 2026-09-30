<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Faq extends Model
{
    protected $fillable = ['question', 'answer', 'faqable_type', 'faqable_id', 'sort_order', 'is_active'];

    protected $casts = ['is_active' => 'boolean', 'sort_order' => 'integer'];

    public const SCOPES = [
        ServiceDetail::class => 'Service',
        Location::class => 'Location',
        BlogDetail::class => 'Article',
    ];

    public function faqable()
    {
        return $this->morphTo();
    }

    public function getScopeLabelAttribute(): string
    {
        return self::SCOPES[$this->faqable_type] ?? 'Global';
    }
}
