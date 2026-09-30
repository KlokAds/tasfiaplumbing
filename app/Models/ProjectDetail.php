<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/** A finished job: proof of real experience, shown in the gallery and on service/location pages. */
class ProjectDetail extends Model
{
    use HasFactory;

    protected $fillable = [
        'name', 'image', 'video', 'service_id', 'location_id', 'area', 'property_type', 'summary', 'completed_on', 'is_active',
    ];

    protected $casts = ['completed_on' => 'date', 'is_active' => 'boolean'];

    public function scopeVisible(Builder $q): Builder
    {
        return $q->where('is_active', true);
    }

    public function service()
    {
        return $this->belongsTo(ServiceDetail::class, 'service_id');
    }

    public function location()
    {
        return $this->belongsTo(Location::class, 'location_id');
    }
}
