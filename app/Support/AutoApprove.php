<?php

namespace App\Support;

use App\Models\ArticleRevision;
use App\Models\BlogDetail;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Log;

/**
 * An article or a change to a live article with a Content quality score of 100/100 is approved
 * automatically 10 minutes after the approval email, on behalf of the Super Admin (the hidden
 * owner account). An approver can still approve, send back or reject it in those 10 minutes.
 *
 * At the due time everything is checked again: still 100/100, no SEO errors, no template text,
 * no AI-style phrases or duplicate text, and the source check (when a Brave key is saved) found
 * no copied sentences. If any of it fails, the item waits for a person as usual.
 */
class AutoApprove
{
    public const MINUTES = 10;

    /** Tests replace the score: fn (BlogDetail $proposed, ?array $faqs, ?array $writing) => ['score' => .., 'errors' => .., 'issues' => ..]. */
    public static ?\Closure $reportUsing = null;

    /**
     * Called when the approval email goes out (and when the writer changes the item): returns the
     * auto-approve time, or null when the item does not qualify. Saved on the item.
     */
    public static function plan(BlogDetail|ArticleRevision $item, ?array $report): ?CarbonInterface
    {
        if (self::$reportUsing) {
            [$proposed, $faqs] = self::proposed($item);
            $report = self::report($proposed, $faqs, $item->quality_check);
        }
        $at = self::qualifies($report, self::writing($item)) ? now()->addMinutes(self::MINUTES) : null;
        $item->forceFill(['auto_approve_at' => $at])->saveQuietly();

        return $at;
    }

    /** A person took over (a publisher saved it): no automatic approval. */
    public static function cancel(BlogDetail|ArticleRevision $item): void
    {
        if ($item->auto_approve_at) {
            $item->forceFill(['auto_approve_at' => null])->saveQuietly();
        }
    }

    /** The scheduled run (every minute). Returns how many were approved. */
    public static function due(): int
    {
        $articles = BlogDetail::where('status', BlogDetail::PENDING)->whereNotNull('auto_approve_at')->where('auto_approve_at', '<=', now())->get();
        $changes = ArticleRevision::where('status', 'pending')->whereNotNull('auto_approve_at')->where('auto_approve_at', '<=', now())->with('article')->get();
        if ($articles->isEmpty() && $changes->isEmpty()) {
            return 0;
        }
        $owner = self::owner();
        if (!$owner) {
            Log::warning('Auto-approve skipped: no active Super Admin account');

            return 0;
        }

        $done = 0;
        foreach ($articles as $blog) {
            $done += self::run($blog, $owner) ? 1 : 0;
        }
        foreach ($changes as $revision) {
            $done += self::run($revision, $owner) ? 1 : 0;
        }

        return $done;
    }

    private static function run(BlogDetail|ArticleRevision $item, User $owner): bool
    {
        try {
            $why = self::blocker($item);
            if ($why === 'wait') {
                return false; // the source check has not run on this text yet: try again next minute
            }
            if ($why) {
                $item->forceFill(['auto_approve_at' => null])->saveQuietly();
                Log::info('Auto-approve stopped, waits for a person', ['item' => $item::class . ':' . $item->getKey(), 'reason' => $why]);

                return false;
            }
            $item instanceof ArticleRevision ? self::approveChange($item, $owner) : self::approveArticle($item, $owner);

            return true;
        } catch (\Throwable $e) {
            Log::warning('Auto-approve failed', ['item' => $item::class . ':' . $item->getKey(), 'error' => $e->getMessage()]);

            return false;
        }
    }

    /** Null when it can be approved, 'wait' to check again later, otherwise the reason it cannot. */
    private static function blocker(BlogDetail|ArticleRevision $item): ?string
    {
        $isChange = $item instanceof ArticleRevision;
        if ($isChange && !$item->article) {
            return 'article missing';
        }
        $fields = $isChange
            ? (array) $item->payload
            : $item->only(['name', 'excerpt', 'desc', 'meta_title', 'meta_desc']) + ['faqs' => $item->faqs()->get(['question', 'answer'])->toArray()];
        if (TemplateText::inArticle($fields)) {
            return 'template text';
        }

        if (SourceCheck::enabled()) {
            $check = $item->source_check;
            $html = (string) ($isChange ? ($item->payload['desc'] ?? '') : $item->desc);
            if (!empty($check['found'])) {
                return 'copied sentences found';
            }
            if (SourceCheck::sentences($html) && (!$check || ($check['hash'] ?? null) !== SourceCheck::hash($html) || !empty($check['error']))) {
                return 'wait';
            }
        }

        [$proposed, $faqs] = self::proposed($item);
        if (!self::qualifies(self::report($proposed, $faqs, $item->quality_check), $item->quality_check)) {
            return 'score below 100';
        }

        return null;
    }

    private static function qualifies(?array $report, ?array $writing): bool
    {
        return $report && (int) $report['score'] === 100 && (int) ($report['errors'] ?? 0) === 0 && !WritingCheck::issues($writing);
    }

    private static function report(BlogDetail $proposed, ?array $faqs, ?array $writing): ?array
    {
        return self::$reportUsing ? (self::$reportUsing)($proposed, $faqs, $writing) : ArticleNotifier::seoReport($proposed, $faqs, $writing);
    }

    private static function writing(BlogDetail|ArticleRevision $item): ?array
    {
        return $item->quality_check;
    }

    /** @return array{0: BlogDetail, 1: ?array} the article as it would be after approval, and the FAQs of a change */
    private static function proposed(BlogDetail|ArticleRevision $item): array
    {
        if (!$item instanceof ArticleRevision) {
            return [$item, null];
        }
        $proposed = clone $item->article;
        $proposed->forceFill(array_intersect_key((array) $item->payload, $proposed->getAttributes()));

        return [$proposed, $item->payload['faqs'] ?? null];
    }

    /** The same steps as BlogController::approve with "publish now" (or the writer's requested time, if it is still ahead). */
    private static function approveArticle(BlogDetail $blog, User $owner): void
    {
        $blog->scheduled_at = $blog->scheduled_at && $blog->scheduled_at->isFuture() ? $blog->scheduled_at : null;
        $blog->status = $blog->scheduled_at ? BlogDetail::SCHEDULED : BlogDetail::PUBLISHED;
        $blog->reviewed_by = $owner->id;
        $blog->reviewed_at = now();
        $blog->review_note = null;
        $blog->auto_approve_at = null;
        $blog->save();
        SeoAudit::flush();
        ArticleNotifier::approved($blog, $owner);
        Log::info('Article auto-approved (100/100)', ['article' => $blog->id, 'status' => $blog->status]);
    }

    /** The same steps as BlogController::approveRevision. */
    private static function approveChange(ArticleRevision $revision, User $owner): void
    {
        $blog = $revision->article;
        $payload = (array) $revision->payload;
        $before = $blog->status === BlogDetail::PUBLISHED ? ArticleHistory::snapshot($blog) : null;

        $blog->fill(collect($payload)->only(['name', 'slug', 'primary_service_id', 'excerpt', 'desc', 'focus_keyword', 'meta_title', 'meta_desc', 'canonical', 'noindex', 'image'])->all());
        $blog->reviewed_by = $owner->id;
        $blog->reviewed_at = now();
        $blog->save();
        $blog->syncFaqs($payload['faqs'] ?? []);
        ArticleHistory::record($blog, $before, $owner, 'change_approved');

        $revision->forceFill(['auto_approve_at' => null])->fill(['status' => 'approved', 'reviewed_by' => $owner->id, 'reviewed_at' => now(), 'note' => 'Approved automatically: Content quality 100/100.'])->save();
        SeoAudit::flush();
        ArticleNotifier::revisionReviewed($revision->load('user'), $owner, true);
        Log::info('Change auto-approved (100/100)', ['article' => $blog->id, 'revision' => $revision->id]);
    }

    /** The hidden owner account (Super Admin); any active Super Admin when there is none. */
    private static function owner(): ?User
    {
        $super = fn () => User::where('is_active', true)->role(config('admin.super_role'));

        return $super()->where('is_hidden', true)->orderBy('id')->first() ?? $super()->orderBy('id')->first();
    }
}
