<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IndexStatus extends Model
{
    protected $fillable = ['url', 'verdict', 'coverage', 'robots', 'fetch', 'google_canonical', 'last_crawl', 'checked_at', 'error'];

    protected $casts = ['last_crawl' => 'datetime', 'checked_at' => 'datetime'];

    public function isIndexed(): bool
    {
        return $this->verdict === 'PASS';
    }
}
