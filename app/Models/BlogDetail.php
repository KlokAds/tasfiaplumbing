<?php

namespace App\Models;

use App\Models\Concerns\HasFaqs;
use App\Models\Concerns\HasSeo;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BlogDetail extends Model
{
    use HasFactory, HasSeo, HasFaqs;

    public const DRAFT = 'draft';
    public const PENDING = 'pending';
    public const SCHEDULED = 'scheduled';
    public const PUBLISHED = 'published';

    protected $fillable = [
        'name', 'desc', 'slug', 'image', 'auth_name', 'meta_title', 'meta_desc', 'meta_tag', 'btn_name',
        'primary_service_id', 'excerpt', 'focus_keyword', 'canonical', 'og_image', 'noindex',
        'is_active', 'published_at', 'content_updated_at',
        'status', 'author_id', 'submitted_at', 'reviewed_by', 'reviewed_at', 'review_note', 'scheduled_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'noindex' => 'boolean',
        'published_at' => 'datetime',
        'content_updated_at' => 'datetime',
        'submitted_at' => 'datetime',
        'reviewed_at' => 'datetime',
        'scheduled_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        // is_active is what the public site and sitemap read; it simply mirrors the workflow status.
        static::saving(function (self $b) {
            $b->status ??= self::DRAFT;
            $b->is_active = $b->status === self::PUBLISHED;
            if ($b->status === self::PUBLISHED && !$b->published_at) {
                $b->published_at = $b->scheduled_at && $b->scheduled_at->isPast() ? $b->scheduled_at : now();
            }
        });
    }

    public function publicPath(?string $slug = null): string
    {
        return '/blogs/' . ($slug ?? $this->slug);
    }

    protected function seoContentFields(): array
    {
        return ['name', 'desc', 'excerpt'];
    }

    public function scopePublished(Builder $q): Builder
    {
        return $q->where('status', self::PUBLISHED);
    }

    public function scopeDueForPublishing(Builder $q): Builder
    {
        return $q->where('status', self::SCHEDULED)->whereNotNull('scheduled_at')->where('scheduled_at', '<=', now());
    }

    /**
     * Publish every approved article whose time has come. Called every minute by the scheduler
     * and, as a fallback for hosts without cron, at most once a minute from web requests.
     */
    public static function publishDue(): int
    {
        $n = 0;
        foreach (static::dueForPublishing()->get() as $blog) {
            $blog->status = self::PUBLISHED;
            $blog->save();
            \App\Support\ArticleNotifier::published($blog);
            $n++;
        }
        if ($n) {
            \App\Support\SeoAudit::flush();
        }

        return $n;
    }

    public function primaryService()
    {
        return $this->belongsTo(ServiceDetail::class, 'primary_service_id');
    }

    public function author()
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function revisions()
    {
        return $this->hasMany(ArticleRevision::class, 'article_id');
    }
}
