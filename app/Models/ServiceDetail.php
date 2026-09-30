<?php

namespace App\Models;

use App\Models\Concerns\HasFaqs;
use App\Models\Concerns\HasSeo;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ServiceDetail extends Model
{
    use HasFactory, HasSeo, HasFaqs;

    protected $fillable = [
        'name', 'order', 'desc', 'btn_name', 'slug', 'image', 'bef_img', 'aft_img',
        'meta_title', 'meta_desc', 'meta_tag',
        'category_id', 'is_active', 'published_at', 'canonical', 'og_image', 'noindex',
        'content_updated_at', 'response_time', 'warranty', 'short_summary', 'focus_keyword',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'noindex' => 'boolean',
        'order' => 'integer',
        'published_at' => 'datetime',
        'content_updated_at' => 'datetime',
    ];

    public function publicPath(?string $slug = null): string
    {
        return '/service/' . ($slug ?? $this->slug);
    }

    protected function seoContentFields(): array
    {
        return ['name', 'desc', 'short_summary', 'response_time', 'warranty'];
    }

    public function category()
    {
        return $this->belongsTo(ServiceCategory::class, 'category_id');
    }

    public function prices()
    {
        return $this->hasMany(Price::class, 'service_id')->orderBy('sort_order');
    }

    public function locations()
    {
        return $this->belongsToMany(Location::class, 'service_location', 'service_id', 'location_id')
            ->withPivot(['intro', 'local_proof', 'is_published'])
            ->withTimestamps();
    }

    public function articles()
    {
        return $this->hasMany(BlogDetail::class, 'primary_service_id');
    }
}
