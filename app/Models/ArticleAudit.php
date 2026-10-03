<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** One live article in the article audit (App\Support\ArticleAuditor). */
class ArticleAudit extends Model
{
    public const ACTIONS = [
        'keep' => 'Keep',
        'update' => 'Update',
        'retarget' => 'New angle',
        'merge' => 'Merge + 301',
        'noindex' => 'Noindex',
    ];

    protected $guarded = ['id'];

    protected $casts = [
        'position' => 'float',
        'decided_at' => 'datetime',
        'computed_at' => 'datetime',
    ];

    public function article()
    {
        return $this->belongsTo(BlogDetail::class, 'article_id');
    }
}
