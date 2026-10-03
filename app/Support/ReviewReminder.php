<?php

namespace App\Support;

use App\Models\ArticleRevision;
use App\Models\BlogDetail;
use App\Models\User;
use App\Notifications\PendingReviewReminder;
use Illuminate\Support\Facades\Notification;

/**
 * Articles and changes that wait for approval too long (config admin.review_reminder_hours)
 * are emailed to the hidden maintenance account (the owner's main mailbox), in one email.
 * Each item is reminded at most once per that many hours, so nobody gets an email per hour.
 */
class ReviewReminder
{
    /** @return int how many items the email listed (0 = nothing sent) */
    public static function run(): int
    {
        $hours = max(1, (int) config('admin.review_reminder_hours', 24));
        $cutoff = now()->subHours($hours);

        $articles = BlogDetail::with('author:id,name')->where('status', BlogDetail::PENDING)
            ->where(fn ($q) => $q->where('submitted_at', '<=', $cutoff)->orWhere(fn ($w) => $w->whereNull('submitted_at')->where('updated_at', '<=', $cutoff)))
            ->get();
        $changes = ArticleRevision::with(['article:id,name', 'user:id,name'])->where('status', 'pending')->where('created_at', '<=', $cutoff)->get();

        // Send only when at least one item has not been reminded in the last $hours.
        $due = $articles->contains(fn ($b) => !$b->reminded_at || $b->reminded_at->lte($cutoff))
            || $changes->contains(fn ($r) => !$r->reminded_at || $r->reminded_at->lte($cutoff));
        $to = User::where('is_hidden', true)->where('is_active', true)->role(config('admin.super_role'))->get()->filter(fn ($u) => filled($u->email));
        if (!$due || $to->isEmpty()) {
            return 0;
        }

        $items = $articles->map(fn ($b) => [
            'kind' => 'New article',
            'title' => $b->name,
            'by' => $b->author?->name ?? $b->auth_name,
            'since' => $b->submitted_at ?? $b->updated_at,
        ])->concat($changes->map(fn ($r) => [
            'kind' => 'Change to a live article',
            'title' => $r->article?->name,
            'by' => $r->user?->name,
            'since' => $r->created_at,
        ]))->sortBy('since')->values();

        Notification::send($to, new PendingReviewReminder($items->all(), $hours));

        BlogDetail::whereKey($articles->modelKeys())->update(['reminded_at' => now()]);
        ArticleRevision::whereKey($changes->modelKeys())->update(['reminded_at' => now()]);

        return $items->count();
    }
}
