<?php

namespace App\Support;

use App\Models\ArticleRevision;
use App\Models\BlogDetail;
use App\Models\User;
use App\Notifications\ArticleAutoSentBack;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Log;

/**
 * The automatic review, 10 minutes after the approval email, on behalf of the Super Admin (the hidden
 * owner account). An approver can still approve, send back or reject it in those 10 minutes.
 *
 * - Content quality 100/100, no SEO errors, no template text, no AI-style phrases or duplicate text,
 *   no title or focus keyword of another article, and the source check (when a Brave key is saved)
 *   found no copied sentences: approved.
 * - Anything else: sent back to the writer with what to fix as the note, and the hidden admin gets an email.
 *
 * Everything is checked again at the due time. auto_approve_at is the time of this automatic review.
 */
class AutoApprove
{
    public const MINUTES = 10;

    /** How long an item may wait for the source check after its time before a person has to decide. */
    private const SOURCE_WAIT_MINUTES = 120;

    /** Tests replace the score: fn (BlogDetail $proposed, ?array $faqs, ?array $writing) => ['score' => .., 'errors' => .., 'issues' => ..]. */
    public static ?\Closure $reportUsing = null;

    /**
     * Called when the approval email goes out (and when the writer changes the item): saves the time of
     * the automatic review on the item. 'approve' says what it would do with the item as it is now.
     *
     * @return array{at: ?CarbonInterface, approve: bool}
     */
    public static function plan(BlogDetail|ArticleRevision $item, ?array $report): array
    {
        if (self::$reportUsing) {
            [$proposed, $faqs] = self::proposed($item);
            $report = self::report($proposed, $faqs, $item->quality_check);
        }
        // No score (the check failed): a person decides.
        $at = $report ? now()->addMinutes(self::MINUTES) : null;
        $item->forceFill(['auto_approve_at' => $at])->saveQuietly();

        return ['at' => $at, 'approve' => $at && self::qualifies($report, $item->quality_check) && !self::clash($item)];
    }

    /** A person took over (a publisher saved it): no automatic review. */
    public static function cancel(BlogDetail|ArticleRevision $item): void
    {
        if ($item->auto_approve_at) {
            $item->forceFill(['auto_approve_at' => null])->saveQuietly();
        }
    }

    /** The scheduled run (every minute). Returns how many were approved or sent back. */
    public static function due(): int
    {
        $articles = BlogDetail::where('status', BlogDetail::PENDING)->whereNotNull('auto_approve_at')->where('auto_approve_at', '<=', now())->get();
        $changes = ArticleRevision::where('status', 'pending')->whereNotNull('auto_approve_at')->where('auto_approve_at', '<=', now())->with('article')->get();
        if ($articles->isEmpty() && $changes->isEmpty()) {
            return 0;
        }
        $owner = self::owner();
        if (!$owner) {
            Log::warning('Automatic review skipped: no active Super Admin account');

            return 0;
        }

        $done = 0;
        foreach ($articles->concat($changes) as $item) {
            $done += self::run($item, $owner) ? 1 : 0;
        }

        return $done;
    }

    private static function run(BlogDetail|ArticleRevision $item, User $owner): bool
    {
        try {
            $found = self::problems($item);
            if ($found['stop']) {
                $item->forceFill(['auto_approve_at' => null])->saveQuietly();
                Log::info('Automatic review stopped, a person decides', ['item' => $item::class . ':' . $item->getKey(), 'reason' => $found['stop']]);

                return false;
            }
            if ($found['problems']) {
                self::sendBack($item, $owner, self::note($found));

                return true;
            }
            if ($found['wait']) {
                // The source check has not run on this text yet: try again next minute, for a while.
                if ($item->auto_approve_at->copy()->addMinutes(self::SOURCE_WAIT_MINUTES)->isPast()) {
                    $item->forceFill(['auto_approve_at' => null])->saveQuietly();
                    Log::info('Automatic review stopped: the source check did not run', ['item' => $item::class . ':' . $item->getKey()]);
                }

                return false;
            }
            $item instanceof ArticleRevision ? self::approveChange($item, $owner) : self::approveArticle($item, $owner);

            return true;
        } catch (\Throwable $e) {
            Log::warning('Automatic review failed', ['item' => $item::class . ':' . $item->getKey(), 'error' => $e->getMessage()]);

            return false;
        }
    }

    /**
     * What stops an approval, as things the writer can fix.
     *
     * @return array{stop: ?string, wait: bool, score: ?int, problems: list<string>}
     */
    private static function problems(BlogDetail|ArticleRevision $item): array
    {
        $out = ['stop' => null, 'wait' => false, 'score' => null, 'problems' => []];
        $isChange = $item instanceof ArticleRevision;
        if ($isChange && !$item->article) {
            return ['stop' => 'article missing'] + $out;
        }
        [$proposed, $faqs] = self::proposed($item);
        $report = self::report($proposed, $faqs, $item->quality_check);
        if (!$report) {
            return ['stop' => 'the score could not be worked out'] + $out;
        }
        $out['score'] = (int) $report['score'];

        $fields = $isChange
            ? (array) $item->payload
            : $item->only(['name', 'excerpt', 'desc', 'meta_title', 'meta_desc']) + ['faqs' => $item->faqs()->get(['question', 'answer'])->toArray()];
        if ($template = TemplateText::inArticle($fields)) {
            $out['problems'][] = TemplateText::message($template);
        }
        if ($clash = self::clash($item)) {
            $out['problems'][] = $clash;
        }

        if (SourceCheck::enabled()) {
            $check = $item->source_check;
            $html = (string) ($isChange ? ($item->payload['desc'] ?? '') : $item->desc);
            if (!empty($check['found'])) {
                $examples = collect($check['matches'] ?? [])->take(3)->map(fn ($m) => '“' . $m['sentence'] . '”')->implode(' ');
                $out['problems'][] = "{$check['found']} sentence(s) are already on other websites (copied text). Rewrite them in your own words, from your own jobs. {$examples}";
            } elseif (SourceCheck::sentences($html) && (!$check || ($check['hash'] ?? null) !== SourceCheck::hash($html) || !empty($check['error']))) {
                $out['wait'] = true;
            }
        }

        if (!self::qualifies($report, $item->quality_check)) {
            foreach (collect($report['issues'] ?? [])->pluck('message')->take(12) as $message) {
                $out['problems'][] = $message;
            }
            if (!($report['issues'] ?? [])) {
                $out['problems'][] = 'Open the article and follow the Content quality checks until the score is 100/100.';
            }
        }

        return $out;
    }

    /** The note the writer gets (and the hidden admin sees). */
    private static function note(array $found): string
    {
        $head = $found['score'] !== null && $found['score'] < 100
            ? "Sent back automatically: Content quality is {$found['score']}/100. It is published automatically at 100/100."
            : 'Sent back automatically: the article cannot be published as it is.';
        $lines = collect($found['problems'])->unique()->values()->map(fn ($p, $i) => ($i + 1) . '. ' . $p);
        $note = $head . "\n\nFix these, then submit it again:\n" . $lines->implode("\n");

        return mb_strlen($note) > 3500 ? mb_substr($note, 0, 3490) . '…' : $note;
    }

    /**
     * The title, SEO title or focus keyword is the same as another article's (live, waiting, or in another
     * change waiting for approval): often the text of one article pasted into another.
     */
    public static function clash(BlogDetail|ArticleRevision $item): ?string
    {
        [$proposed] = self::proposed($item);
        $norm = fn ($v) => mb_strtolower(trim(preg_replace('/\s+/u', ' ', (string) $v)));
        $mine = collect([$proposed->name, $proposed->meta_title])->map($norm)->filter()->unique();
        $keyword = $norm($proposed->focus_keyword);

        $others = BlogDetail::query()->whereKeyNot($proposed->getKey())->where('status', '!=', 'merged')
            ->get(['name', 'meta_title', 'focus_keyword'])->map(fn ($b) => $b->only(['name', 'meta_title', 'focus_keyword']))
            ->concat(ArticleRevision::where('status', 'pending')->where('article_id', '!=', $proposed->getKey())
                ->when($item instanceof ArticleRevision, fn ($q) => $q->whereKeyNot($item->getKey()))
                ->get(['payload'])->map(fn ($r) => array_intersect_key((array) $r->payload, array_flip(['name', 'meta_title', 'focus_keyword']))));

        foreach ($others as $o) {
            $name = '“' . ($o['name'] ?? '') . '”';
            if ($mine->intersect([$norm($o['name'] ?? ''), $norm($o['meta_title'] ?? '')])->filter()->isNotEmpty()) {
                return "The title is the same as another article, {$name}. If this text belongs to that article, put it there; otherwise give this one its own title.";
            }
            if ($keyword !== '' && $keyword === $norm($o['focus_keyword'] ?? '')) {
                return "The focus keyword “{$proposed->focus_keyword}” is the same as another article, {$name}. Two pages on one keyword compete in Google: if this text belongs to that article, put it there; otherwise choose another keyword.";
            }
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

    /** The same steps as BlogController::reject / rejectRevision, with the note, plus an email to the hidden admin. */
    private static function sendBack(BlogDetail|ArticleRevision $item, User $owner, string $note): void
    {
        if ($item instanceof ArticleRevision) {
            $item->forceFill(['auto_approve_at' => null])->fill(['status' => 'rejected', 'note' => $note, 'reviewed_by' => $owner->id, 'reviewed_at' => now()])->save();
            ArticleNotifier::revisionReviewed($item->load('article', 'user'), $owner, false);
            $title = (string) ($item->payload['name'] ?? $item->article->name);
            $by = $item->user?->name;
            $kind = 'a change to a live article';
            $url = url("/admin/blogs?tab=all&edit={$item->article_id}");
        } else {
            $item->forceFill(['auto_approve_at' => null])->fill(['status' => BlogDetail::DRAFT, 'reviewed_by' => $owner->id, 'reviewed_at' => now(), 'review_note' => $note])->save();
            ArticleNotifier::rejected($item, $owner, $note);
            $title = (string) $item->name;
            $by = $item->author?->name ?? $item->auth_name;
            $kind = 'a new article';
            $url = url("/admin/blogs?tab=all&edit={$item->id}");
        }
        Log::info('Sent back automatically', ['item' => $item::class . ':' . $item->getKey()]);

        $admins = User::where('is_hidden', true)->where('is_active', true)->role(config('admin.super_role'))->get()->filter(fn ($u) => filled($u->email));
        foreach ($admins as $admin) {
            try {
                $admin->notify(new ArticleAutoSentBack($title, $kind, $by, $note, $url));
            } catch (\Throwable $e) {
                Log::warning('Sent-back email to the hidden admin failed', ['user' => $admin->id, 'error' => $e->getMessage()]);
            }
        }
    }

    /** The hidden owner account (Super Admin); any active Super Admin when there is none. */
    private static function owner(): ?User
    {
        $super = fn () => User::where('is_active', true)->role(config('admin.super_role'));

        return $super()->where('is_hidden', true)->orderBy('id')->first() ?? $super()->orderBy('id')->first();
    }
}
