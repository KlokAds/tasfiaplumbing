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
        self::send(self::publishers($by), new ArticleWorkflow(
            'submitted', $blog->name, self::adminUrl($blog, 'review'), $by->name, null, self::when($blog->scheduled_at),
            seo: self::seoReport($blog),
        ));
    }

    public static function approved(BlogDetail $blog, User $by): void
    {
        $event = $blog->status === BlogDetail::SCHEDULED ? 'scheduled' : 'approved';
        self::send(self::author($blog, $by), new ArticleWorkflow(
            $event, $blog->name, self::adminUrl($blog), $by->name, null, self::when($blog->scheduled_at), url($blog->publicPath()),
        ));
    }

    public static function rejected(BlogDetail $blog, User $by, string $note): void
    {
        self::send(self::author($blog, $by), new ArticleWorkflow(
            'rejected', $blog->name, self::adminUrl($blog, 'mine'), $by->name, $note,
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
        $proposed = clone $revision->article;
        $proposed->forceFill(array_intersect_key((array) $revision->payload, $proposed->getAttributes()));

        self::send(self::publishers($by), new ArticleWorkflow(
            'revision_submitted', $revision->article->name, url('/admin/blogs?tab=review'), $by->name,
            seo: self::seoReport($proposed, $revision->payload['faqs'] ?? null),
            changes: ArticleChanges::between($revision->article, (array) $revision->payload),
        ));
    }

    public static function revisionReviewed(ArticleRevision $revision, User $by, bool $approved): void
    {
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
    public static function seoReport(BlogDetail $blog, ?array $faqs = null): ?array
    {
        try {
            $title = Str::lower(trim($blog->meta_title ?: $blog->name));
            $taken = BlogDetail::query()->whereKeyNot($blog->getKey())->get(['name', 'meta_title'])
                ->contains(fn ($r) => Str::lower(trim($r->meta_title ?: $r->name)) === $title);
            $audit = SeoAudit::article($blog, $taken ? [$title => 2] : []);
            $quality = ContentQuality::forArticle($blog, $faqs);
            $short = ['seo' => 'SEO', 'aeo' => 'AEO', 'geo' => 'GEO', 'eeat' => 'E-E-A-T'];

            $errors = collect($audit['issues'])->where('level', 'error')->map(fn ($i) => ['level' => 'error', 'message' => $i['message']]);
            $tips = collect($quality['pillars'])->flatMap(fn ($p, $key) => collect($p['checks'])->reject(fn ($c) => $c['ok'])
                ->map(fn ($c) => ['level' => 'warning', 'message' => ($short[$key] ?? strtoupper($key)) . ': ' . $c['label'] . '. ' . $c['tip']]));

            return [
                'score' => (int) $quality['score'],
                'errors' => $errors->count(),
                'warnings' => $tips->count(),
                'words' => SeoAudit::wordCount($blog->desc),
                'issues' => $errors->concat($tips)->values()->all(),
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
