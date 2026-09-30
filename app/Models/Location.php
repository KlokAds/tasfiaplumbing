<?php

namespace App\Models;

use App\Models\Concerns\HasFaqs;
use App\Models\Concerns\HasSeo;
use Illuminate\Database\Eloquent\Model;

class Location extends Model
{
    use HasSeo, HasFaqs;

    public const REGIONS = ['Central', 'East', 'West', 'North', 'North-East'];
    public const PROPERTY_TYPES = ['HDB', 'Condominium', 'Landed', 'Commercial', 'Industrial'];

    protected $fillable = [
        'name', 'slug', 'region', 'intro', 'description', 'property_types', 'nearby_areas',
        'latitude', 'longitude', 'image', 'sort_order', 'is_active', 'is_featured',
        'meta_title', 'meta_desc', 'canonical', 'noindex', 'content_updated_at',
    ];

    protected $casts = [
        'property_types' => 'array',
        'nearby_areas' => 'array',
        'is_active' => 'boolean',
        'is_featured' => 'boolean',
        'noindex' => 'boolean',
        'sort_order' => 'integer',
        'latitude' => 'float',
        'longitude' => 'float',
        'content_updated_at' => 'datetime',
    ];

    public function publicPath(?string $slug = null): string
    {
        return '/locations/' . ($slug ?? $this->slug);
    }

    protected function seoContentFields(): array
    {
        return ['name', 'intro', 'description', 'property_types'];
    }

    public function services()
    {
        return $this->belongsToMany(ServiceDetail::class, 'service_location', 'location_id', 'service_id')
            ->withPivot(['intro', 'local_proof', 'is_published'])
            ->withTimestamps();
    }
}
