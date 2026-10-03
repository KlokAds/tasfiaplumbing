<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ArticleRevision extends Model
{
    protected $fillable = ['article_id', 'user_id', 'payload', 'status', 'note', 'reviewed_by', 'reviewed_at'];

    protected $casts = ['payload' => 'array', 'reviewed_at' => 'datetime', 'reminded_at' => 'datetime'];

    public function article()
    {
        return $this->belongsTo(BlogDetail::class, 'article_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
