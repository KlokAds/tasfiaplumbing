<?php

namespace App\Support;

use App\Models\ArticleRevision;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Releases the imported AI rewrites (changes waiting for approval, note starting with RewriteImport::MARK)
 * a few per day instead of all at once: PER_DAY per day, one at each time in SLOTS (Singapore time),
 * oldest first. A missed run catches up later the same day; a missed day is not made up.
 *
 * A rewrite is held (left waiting for a person) when it has template text, its title or focus keyword is
 * the same as another article's, or it promises a round-the-clock service (24/7, 24 hours), which the
 * owner has not confirmed. Held rewrites are listed by articles:release-daily --dry.
 *
 * Each release takes the same steps as "Approve changes" (BlogController::approveRevision), so the
 * article's History keeps the old version and it can be put back from there.
 */
class DailyRelease
{
    public const PER_DAY = 4;

    /** Hours (Singapore time) at which one rewrite is released. */
    public const SLOTS = [9, 12, 15, 18];

    public const NOTE = 'Released by the daily schedule (' . self::PER_DAY . ' a day).';

    private const FIELDS = ['name', 'slug', 'primary_service_id', 'excerpt', 'desc', 'focus_keyword', 'meta_title', 'meta_desc', 'canonical', 'noindex', 'image'];

    private const ROUND_THE_CLOCK = '/\b24\s*\/\s*7\b|\b24[- ]hours?\b|\bround[- ]the[- ]clock\b/i';

    /** How many may be released by now today (one per slot that has passed). */
    public static function allowedSoFar(?CarbonInterface $now = null): int
    {
        $hour = ($now ?? now())->copy()->setTimezone(self::timezone())->hour;

        return min(self::PER_DAY, count(array_filter(self::SLOTS, fn ($h) => $hour >= $h)));
    }

    /** Released today (Singapore day). */
    public static function releasedToday(): int
    {
        $start = now(self::timezone())->startOfDay()->utc();

        return ArticleRevision::where('status', 'approved')->where('reviewed_at', '>=', $start)->where('note', self::NOTE)->count();
    }

    /** The waiting AI rewrites, oldest first. */
    public static function queue(): Collection
    {
        return ArticleRevision::where('status', 'pending')->where('note', 'like', RewriteImport::MARK . '%')
            ->with('article')->orderBy('id')->get();
    }

    /** Why a rewrite is held for a person, or null when it can be released. */
    public static function holdReason(ArticleRevision $revision): ?string
    {
        if (!$revision->article) {
            return 'the article no longer exists';
        }
        $payload = (array) $revision->payload;
        if (TemplateText::inArticle($payload)) {
            return 'template text';
        }
        if (AutoApprove::clash($revision)) {
            return 'same title or focus keyword as another article';
        }
        $text = strip_tags(implode(' ', array_map(fn ($v) => is_array($v) ? json_encode($v, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : (string) $v, $payload)));
        if (preg_match(self::ROUND_THE_CLOCK, $text, $m)) {
            return "promises a round-the-clock service (\"{$m[0]}\"), not confirmed";
        }

        return null;
    }

    /**
     * The scheduled run: releases what is due now. $dry only says what it would do.
     *
     * @return array{released: list<string>, held: array<int, string>, waiting: int}
     */
    public static function run(bool $dry = false): array
    {
        $out = ['released' => [], 'held' => [], 'waiting' => 0];
        $due = max(0, self::allowedSoFar() - self::releasedToday());
        $owner = $due > 0 && !$dry ? self::owner() : null;
        if ($due > 0 && !$dry && !$owner) {
            Log::warning('Daily release skipped: no active Super Admin account');
            $due = 0;
        }

        foreach (self::queue() as $revision) {
            if ($reason = self::holdReason($revision)) {
                $out['held'][$revision->id] = ($revision->payload['name'] ?? $revision->article?->name) . ': ' . $reason;
                continue;
            }
            if ($due > 0) {
                $dry || self::release($revision, $owner);
                $out['released'][] = (string) ($revision->payload['name'] ?? $revision->article->name);
                $due--;
                continue;
            }
            $out['waiting']++;
        }
        if ($out['released'] && !$dry) {
            SeoAudit::flush();
        }

        return $out;
    }

    /** The same steps as BlogController::approveRevision, by the hidden Super Admin, without emails. */
    private static function release(ArticleRevision $revision, User $owner): void
    {
        $blog = $revision->article;
        $payload = (array) $revision->payload;
        $before = $blog->status === \App\Models\BlogDetail::PUBLISHED ? ArticleHistory::snapshot($blog) : null;

        $blog->fill(collect($payload)->only(self::FIELDS)->all());
        $blog->reviewed_by = $owner->id;
        $blog->reviewed_at = now();
        $blog->save();
        $blog->syncFaqs($payload['faqs'] ?? []);
        ArticleHistory::record($blog, $before, $owner, 'change_approved');

        $revision->forceFill(['auto_approve_at' => null])->fill(['status' => 'approved', 'reviewed_by' => $owner->id, 'reviewed_at' => now(), 'note' => self::NOTE])->save();
        Log::info('Rewrite released by the daily schedule', ['article' => $blog->id, 'revision' => $revision->id]);
    }

    private static function timezone(): string
    {
        return (string) config('app.schedule_timezone', 'Asia/Singapore');
    }

    /** The hidden owner account (Super Admin); any active Super Admin when there is none. */
    private static function owner(): ?User
    {
        $super = fn () => User::where('is_active', true)->role(config('admin.super_role'));

        return $super()->where('is_hidden', true)->orderBy('id')->first() ?? $super()->orderBy('id')->first();
    }
}
