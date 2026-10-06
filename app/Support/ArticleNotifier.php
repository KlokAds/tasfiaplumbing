<?php

namespace App\Support;

use App\Models\ArticleRevision;
use App\Models\BlogDetail;
use App\Models\User;
use App\Notifications\ArticleWorkflow;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Sends workflow notifications (bell + email). A mail server problem must never
 * block publishing, so every send is isolated and failures are only logged.
 */
class ArticleNotifier
{
    public static function submitted(BlogDetail $blog, User $by): void
    {
        $seo = self::seoReport($blog, null, $blog->quality_check);
        $auto = AutoApprove::plan($blog, $seo);
        self::sendToApprovers($by, new ArticleWorkflow(
            'submitted', $blog->name, self::adminUrl($blog, 'review'), $by->name, null, self::when($blog->scheduled_at),
            seo: $seo, autoApprove: $auto['at'] && $auto['approve'] ? self::when($auto['at']) : null,
            autoSendBack: $auto['at'] && !$auto['approve'] ? self::when($auto['at']) : null,
        ));
    }

    /**
     * The writer changed an article or a change after submitting it: the approvers hear about it,
     * at most once an hour per item, so a writer fixing typos does not fill their inbox.
     */
    public static function updatedAfterSubmit(BlogDetail|ArticleRevision $item, User $by): void
    {
        $isChange = $item instanceof ArticleRevision;
        if ($isChange) {
            $proposed = self::proposed($item);
            $seo = self::seoReport($proposed, $item->payload['faqs'] ?? null, $item->quality_check);
        } else {
            $seo = self::seoReport($item, null, $item->quality_check);
        }
        // Every change restarts the 10 minutes of the automatic approval (or stops it below 100/100).
        $auto = AutoApprove::plan($item, $seo);
        if (!\Illuminate\Support\Facades\Cache::add('article-updated:' . ($isChange ? 'r' : 'a') . $item->getKey(), 1, now()->addHour())) {
            return;
        }
        if ($isChange) {
            self::sendToApprovers($by, new ArticleWorkflow(
                'revision_updated', $item->article->name, url('/admin/blogs?tab=review'), $by->name,
                seo: $seo,
                changes: ArticleChanges::between($item->article, (array) $item->payload),
                autoApprove: $auto['at'] && $auto['approve'] ? self::when($auto['at']) : null,
            autoSendBack: $auto['at'] && !$auto['approve'] ? self::when($auto['at']) : null,
            ));

            return;
        }
        self::sendToApprovers($by, new ArticleWorkflow(
            'updated', $item->name, self::adminUrl($item, 'review'), $by->name, null, self::when($item->scheduled_at),
            seo: $seo, autoApprove: $auto['at'] && $auto['approve'] ? self::when($auto['at']) : null,
            autoSendBack: $auto['at'] && !$auto['approve'] ? self::when($auto['at']) : null,
        ));
    }

    /** $auto: approved by App\Support\AutoApprove (10 minutes after the approval email). */
    public static function approved(BlogDetail $blog, User $by, bool $auto = false): void
    {
        $event = $blog->status === BlogDetail::SCHEDULED ? 'scheduled' : 'approved';
        self::send(self::author($blog, $by), new ArticleWorkflow(
            $event, $blog->name, self::adminUrl($blog), $by->name, null, self::when($blog->scheduled_at), url($blog->publicPath()),
        ));
        self::tellOwners($by, $auto, new \App\Notifications\ArticleDecision(
            $event, $blog->name, $blog->author?->name ?? $blog->auth_name, $by->name, $auto, self::adminUrl($blog),
            null, url($blog->publicPath()), self::when($blog->scheduled_at),
        ));
    }

    public static function rejected(BlogDetail $blog, User $by, string $note, bool $auto = false): void
    {
        self::send(self::author($blog, $by), new ArticleWorkflow(
            'rejected', $blog->name, self::adminUrl($blog, 'mine'), $by->name, $note,
        ));
        self::tellOwners($by, $auto, new \App\Notifications\ArticleDecision(
            'sent_back', $blog->name, $blog->author?->name ?? $blog->auth_name, $by->name, $auto, self::adminUrl($blog), $note,
        ));
    }

    public static function published(BlogDetail $blog): void
    {
        self::send(self::author($blog), new ArticleWorkflow(
            'published', $blog->name, self::adminUrl($blog), null, null, null, url($blog->publicPath()),
        ));
    }

    public static function revisionSubmitted(ArticleRevision $revision, User $by): void
    {
        $proposed = self::proposed($revision);

        $seo = self::seoReport($proposed, $revision->payload['faqs'] ?? null, $revision->quality_check);
        $auto = AutoApprove::plan($revision, $seo);
        self::sendToApprovers($by, new ArticleWorkflow(
            'revision_submitted', $revision->article->name, url('/admin/blogs?tab=review'), $by->name,
            seo: $seo,
            changes: ArticleChanges::between($revision->article, (array) $revision->payload),
            autoApprove: $auto['at'] && $auto['approve'] ? self::when($auto['at']) : null,
            autoSendBack: $auto['at'] && !$auto['approve'] ? self::when($auto['at']) : null,
        ));
    }

    public static function revisionReviewed(ArticleRevision $revision, User $by, bool $approved, bool $auto = false): void
    {
        self::tellOwners($by, $auto, new \App\Notifications\ArticleDecision(
            $approved ? 'change_approved' : 'change_rejected', $revision->article->name, $revision->user?->name, $by->name, $auto,
            self::adminUrl($revision->article), $approved ? null : $revision->note, url($revision->article->publicPath()),
        ));
        $user = $revision->user;
        if (!$user || $user->id === $by->id) {
            return;
        }
        self::send(collect([$user]), new ArticleWorkflow(
            $approved ? 'revision_approved' : 'revision_rejected',
            $revision->article->name,
            self::adminUrl($revision->article, 'mine'),
            $by->name,
            $approved ? null : $revision->note,
            null,
            url($revision->article->publicPath()),
        ));
    }

    /** The source check found sentences on other websites: the writer, the approvers and the hidden admin (the owner's mailbox). */
    public static function sourceFound(BlogDetail|ArticleRevision $item, array $result): void
    {
        $isChange = $item instanceof ArticleRevision;
        $title = $isChange ? (string) ($item->payload['name'] ?? $item->article?->name) : (string) $item->name;
        $by = $isChange ? $item->user?->name : ($item->author?->name ?? $item->auth_name);
        $owners = User::where('is_hidden', true)->where('is_active', true)->role(config('admin.super_role'))->get();

        $writer = $isChange ? $item->user : $item->author;
        foreach (self::publishers()->merge($owners)->push($writer)->filter()->unique('id')->filter(fn ($u) => filled($u->email) && $u->is_active !== false) as $user) {
            try {
                $user->notify(new \App\Notifications\SourceCheckFound($title, $isChange ? 'a change to a live article' : 'a new article', $by, $result));
            } catch (\Throwable $e) {
                Log::warning('Source check email failed', ['user' => $user->id, 'error' => $e->getMessage()]);
            }
        }
    }

    /**
     * The live article as it would be once a change is approved (for the scores). Approving new text
     * makes it "updated now" (HasSeo), so the freshness check counts it as updated.
     */
    public static function proposed(ArticleRevision $revision): BlogDetail
    {
        $proposed = clone $revision->article;
        $proposed->forceFill(array_intersect_key((array) $revision->payload, $proposed->getAttributes()));
        if ((string) ($revision->payload['desc'] ?? '') !== (string) $revision->article->desc) {
            $proposed->content_updated_at = now();
        }

        return $proposed;
    }

    /** Everyone who writes or approves articles, and the hidden Super Admin (for the weekly content plan). */
    public static function team(): Collection
    {
        $users = self::publishers();
        try {
            $users = $users->merge(User::visible()->permission('articles.create')->get());
        } catch (\Throwable) {
            // permission not seeded yet
        }

        return $users->merge(self::owners())->unique('id')
            ->filter(fn (User $u) => $u->is_active !== false && filled($u->email))->values();
    }

    /** The hidden Super Admin account(s): the owner's mailbox, the only one that gets emails about approvals. */
    public static function owners(): Collection
    {
        return User::where('is_hidden', true)->where('is_active', true)->role(config('admin.super_role'))->get();
    }

    /**
     * Something for the approvers: the hidden Super Admin gets the email (and the bell); the other
     * approvers see it in the bell only.
     */
    private static function sendToApprovers(?User $by, ArticleWorkflow $notification): void
    {
        $bellOnly = clone $notification;
        $bellOnly->withMail = false;
        self::send(self::publishers($by), $bellOnly);
        self::send(self::owners()->reject(fn (User $u) => $u->id === $by?->id), $notification);
    }

    /**
     * Every approval and send-back is emailed to the hidden Super Admin. Not when they decided it
     * themselves, except an automatic decision (made on their behalf).
     */
    private static function tellOwners(User $by, bool $auto, \App\Notifications\ArticleDecision $notification): void
    {
        foreach (self::owners()->filter(fn (User $u) => filled($u->email) && ($auto || $u->id !== $by->id)) as $owner) {
            try {
                $owner->notify($notification);
            } catch (\Throwable $e) {
                Log::warning('Article decision email to the hidden admin failed', ['user' => $owner->id, 'error' => $e->getMessage()]);
            }
        }
    }

    /** Everyone who can approve: Super Admins plus any role given articles.publish. */
    public static function publishers(?User $except = null): Collection
    {
        $users = User::visible()->role(config('admin.super_role'))->get();
        try {
            $users = $users->merge(User::visible()->permission('articles.publish')->get());
        } catch (\Throwable $e) {
            // permission not seeded yet
        }


        return $users->unique('id')
            ->filter(fn (User $u) => $u->is_active !== false && $u->id !== $except?->id)
            ->values();
    }

    /**
     * The same SEO check the admin list shows, so the approver sees the quality before opening the article.
     * Returns null when the check cannot run; the email is then sent without it.
     */
    /**
     * The scores in the approval email: the same as the article editor (the Content quality
     * score and its SEO, AEO, GEO and E-E-A-T parts), the SEO errors, and every check that
     * fails with what to do. $faqs: the FAQs of a change waiting for approval.
     */
    public static function seoReport(BlogDetail $blog, ?array $faqs = null, ?array $writing = null): ?array
    {
        try {
            $title = Str::lower(trim($blog->meta_title ?: $blog->name));
            $taken = BlogDetail::query()->whereKeyNot($blog->getKey())->get(['name', 'meta_title'])
                ->contains(fn ($r) => Str::lower(trim($r->meta_title ?: $r->name)) === $title);
            $audit = SeoAudit::article($blog, $taken ? [$title => 2] : []);
            $quality = ContentQuality::forArticle($blog, $faqs);
            $short = ['seo' => 'SEO', 'aeo' => 'AEO', 'geo' => 'GEO', 'eeat' => 'E-E-A-T'];

            $errors = collect($audit['issues'])->where('level', 'error')->map(fn ($i) => ['level' => 'error', 'message' => $i['message']]);
            // The free writing check first: AI-style phrases and duplicate text.
            $writingIssues = collect(WritingCheck::issues($writing))->map(fn ($m) => ['level' => 'warning', 'message' => $m]);
            $tips = collect($quality['pillars'])->flatMap(fn ($p, $key) => collect($p['checks'])->reject(fn ($c) => $c['ok'])
                ->map(fn ($c) => ['level' => 'warning', 'message' => ($short[$key] ?? strtoupper($key)) . ': ' . $c['label'] . '. ' . $c['tip']]));

            return [
                'score' => (int) $quality['score'],
                'errors' => $errors->count(),
                'warnings' => $tips->count() + $writingIssues->count(),
                'words' => SeoAudit::wordCount($blog->desc),
                'issues' => $errors->concat($writingIssues)->concat($tips)->values()->all(),
                'pillars' => collect($quality['pillars'])
                    ->map(fn ($p, $key) => ['key' => $key, 'label' => $short[$key] ?? strtoupper($key), 'name' => $p['label'], 'score' => (int) $p['score']])
                    ->values()->all(),
            ];
        } catch (\Throwable $e) {
            Log::warning('SEO report for article email failed', ['article' => $blog->getKey(), 'error' => $e->getMessage()]);

            return null;
        }
    }

    public static function when(?CarbonInterface $at): ?string
    {
        return $at?->copy()->timezone(config('admin.timezone'))->format('D j M Y, g:i A') . ($at ? ' (' . config('admin.timezone_label') . ')' : '');
    }

    private static function author(BlogDetail $blog, ?User $except = null): Collection
    {
        $author = $blog->author;

        return $author && $author->id !== $except?->id && $author->is_active !== false ? collect([$author]) : collect();
    }

    private static function adminUrl(BlogDetail $blog, string $tab = 'all'): string
    {
        return url("/admin/blogs?tab={$tab}&edit={$blog->id}");
    }

    private static function send(Collection $users, ArticleWorkflow $notification): void
    {
        foreach ($users as $user) {
            try {
                $user->notify($notification);
            } catch (\Throwable $e) {
                // The database channel runs first, so the bell notification is already stored.
                Log::warning('Article notification email failed', ['user' => $user->id, 'event' => $notification->event, 'error' => $e->getMessage()]);
            }
        }
    }
}
