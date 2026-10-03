<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** A live version of an article that was replaced (see App\Support\ArticleHistory). */
class ArticleVersion extends Model
{
    protected $fillable = ['article_id', 'user_id', 'event', 'payload'];

    protected $casts = ['payload' => 'array'];

    public function article()
    {
        return $this->belongsTo(BlogDetail::class, 'article_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
