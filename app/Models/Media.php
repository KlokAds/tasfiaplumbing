<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Media extends Model
{
    protected $table = 'media';

    protected $fillable = ['path', 'original_name', 'mime', 'size', 'width', 'height', 'alt', 'uploaded_by'];

    protected $casts = ['size' => 'integer', 'width' => 'integer', 'height' => 'integer'];
}
