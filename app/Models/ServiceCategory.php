<?php

namespace App\Models;

use App\Models\Concerns\HasSeo;
use Illuminate\Database\Eloquent\Model;

class ServiceCategory extends Model
{
    use HasSeo;

    protected $fillable = [
        'name', 'slug', 'intro', 'description', 'image', 'sort_order', 'is_active',
        'meta_title', 'meta_desc', 'canonical', 'noindex', 'content_updated_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'noindex' => 'boolean',
        'sort_order' => 'integer',
        'content_updated_at' => 'datetime',
    ];

    public function publicPath(?string $slug = null): string
    {
        return '/services/' . ($slug ?? $this->slug);
    }

    protected function seoContentFields(): array
    {
        return ['name', 'intro', 'description'];
    }

    public function services()
    {
        return $this->hasMany(ServiceDetail::class, 'category_id')->orderBy('order');
    }
}
