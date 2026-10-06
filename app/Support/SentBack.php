<?php

namespace App\Support;

use App\Models\ArticleRevision;
use App\Models\BlogDetail;
use Illuminate\Support\Collection;

/**
 * What was sent back to writers (by an approver or automatically) and is still waiting for them:
 * the Articles "Sent back" tab and the red count next to Articles in the sidebar.
 */
class SentBack
{
    /**
     * Changes sent back that nobody has acted on yet, by article id: the writer's latest change to
     * that article, and the article has not been changed since. $userId: only that writer's.
     */
    public static function changes(?int $userId): Collection
    {
        $rejected = ArticleRevision::where('status', 'rejected')->when($userId, fn ($q) => $q->where('user_id', $userId))
            ->with(['article:id,content_updated_at', 'user:id,name'])->latest('id')
            ->get(['id', 'article_id', 'user_id', 'note', 'reviewed_at']);
        if ($rejected->isEmpty()) {
            return collect();
        }
        $latest = ArticleRevision::whereIn('article_id', $rejected->pluck('article_id')->unique())
            ->selectRaw('article_id, user_id, MAX(id) as latest_id')->groupBy('article_id', 'user_id')->get()
            ->mapWithKeys(fn ($r) => [$r->article_id . '-' . $r->user_id => (int) $r->latest_id]);

        return $rejected->filter(fn (ArticleRevision $r) => $r->article
            && ($latest[$r->article_id . '-' . $r->user_id] ?? $r->id) === $r->id
            && !($r->article->content_updated_at && $r->reviewed_at && $r->article->content_updated_at->gt($r->reviewed_at)))
            ->unique('article_id')->keyBy('article_id');
    }

    /** New articles sent back to the writer (back in drafts with the reviewer's note). */
    public static function articles()
    {
        return BlogDetail::where('status', BlogDetail::DRAFT)->whereNotNull('review_note')->where('review_note', '!=', '');
    }

    /** How many are waiting for this writer to fix and resubmit. */
    public static function countFor(int $userId): int
    {
        return self::articles()->where('author_id', $userId)->count() + self::changes($userId)->count();
    }
}
