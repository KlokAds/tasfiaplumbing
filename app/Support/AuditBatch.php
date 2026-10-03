<?php

namespace App\Support;

use App\Models\ArticleAudit;
use App\Models\BlogDetail;
use App\Models\Redirect;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Runs the approved audit decisions, only when an approver presses "Run batch", at most
 * BATCH_SIZE at a time. Nothing is deleted:
 * - merge: a 301 redirect to the stronger article (or the service page) and the article is set to
 *   "merged" (off the site and the sitemap, kept in the database);
 * - noindex: the article stays live with noindex;
 * - update / new angle: no change on the site, the article goes on the writing to-do list;
 * - keep: marked as done.
 * What each article was before is stored with it, so every step can be undone.
 */
class AuditBatch
{
    public static function size(): int
    {
        return max(1, (int) config('admin.audit.batch_size', 50));
    }

    /** Decided, not run yet, and the article is still live. */
    public static function waiting(): Builder
    {
        return ArticleAudit::query()->whereNotNull('decision')->whereNull('applied_at')
            ->whereHas('article', fn ($q) => $q->where('status', BlogDetail::PUBLISHED));
    }

    /** @return array{done: array<string, int>, skipped: list<string>} */
    public static function run(User $by): array
    {
        $done = [];
        $skipped = [];
        foreach (self::waiting()->with('article')->orderBy('decided_at')->limit(self::size())->get() as $audit) {
            try {
                $action = DB::transaction(fn () => self::apply($audit, $by));
                $done[$action] = ($done[$action] ?? 0) + 1;
            } catch (\RuntimeException $e) {
                $skipped[] = "“{$audit->article->name}”: " . $e->getMessage();
            }
        }
        if ($done) {
            SeoAudit::flush();
            Log::info('Article audit batch', ['by' => $by->email, 'done' => $done, 'skipped' => count($skipped)]);
        }

        return ['done' => $done, 'skipped' => $skipped];
    }

    private static function apply(ArticleAudit $audit, User $by): string
    {
        $blog = $audit->article;
        $snapshot = ['status' => $blog->status, 'noindex' => (bool) $blog->noindex];

        if ($audit->decision === 'merge') {
            $to = self::mergeTarget($audit);
            $paths = array_values(array_unique([$blog->publicPath(), '/blog/' . $blog->slug]));
            $snapshot['redirects'] = [];
            foreach ($paths as $from) {
                $existing = Redirect::where('from_path', $from)->first();
                $snapshot['redirects'][] = ['from' => $from, 'before' => $existing?->only(['to_path', 'code', 'source', 'is_active', 'notes'])];
                Redirect::updateOrCreate(['from_path' => $from], [
                    'to_path' => $to, 'code' => 301, 'source' => 'audit', 'is_active' => true,
                    'notes' => 'Merged by the article audit into ' . $to,
                ]);
            }
            // Older redirects that led to this article now go straight to the new page (no chains).
            $snapshot['repointed'] = Redirect::whereIn('to_path', $paths)->whereNotIn('from_path', $paths)->pluck('to_path', 'id')->all();
            foreach (Redirect::whereKey(array_keys($snapshot['repointed']))->get() as $r) {
                $r->update(['to_path' => $to]);
            }
            $blog->forceFill(['status' => BlogDetail::MERGED])->save();
            $snapshot['to'] = $to;
        } elseif ($audit->decision === 'noindex') {
            $blog->forceFill(['noindex' => true])->save();
        }

        $audit->update([
            'applied_action' => $audit->decision,
            'applied_at' => now(),
            'applied_by' => $by->id,
            'applied_snapshot' => $snapshot,
            'undone_at' => null,
        ]);

        return $audit->decision;
    }

    private static function mergeTarget(ArticleAudit $audit): string
    {
        if ($audit->decision_target_id) {
            $target = BlogDetail::find($audit->decision_target_id);
            if (!$target || $target->status !== BlogDetail::PUBLISHED) {
                throw new \RuntimeException('the article to merge into is not live (merged or unpublished). Choose another one.');
            }

            return $target->publicPath();
        }
        $service = $audit->service_id ? \App\Models\ServiceDetail::where('is_active', true)->find($audit->service_id) : null;
        if (!$service) {
            throw new \RuntimeException('no article or live service page to merge into.');
        }

        return $service->publicPath();
    }

    /** Put one article back as it was before the batch; its decision is cleared. */
    public static function undo(ArticleAudit $audit, User $by): void
    {
        DB::transaction(function () use ($audit, $by) {
            $s = (array) $audit->applied_snapshot;
            $blog = $audit->article;

            if ($audit->applied_action === 'merge') {
                foreach ($s['redirects'] ?? [] as $r) {
                    $row = Redirect::where('from_path', $r['from'])->first();
                    if ($row && $r['before']) {
                        $row->update($r['before']);
                    } elseif ($row) {
                        $row->update(['is_active' => false, 'notes' => trim(($row->notes ?? '') . ' (Undone ' . now()->toDateString() . ')')]);
                    }
                }
                foreach ($s['repointed'] ?? [] as $id => $old) {
                    Redirect::whereKey($id)->first()?->update(['to_path' => $old]);
                }
                $blog->forceFill(['status' => $s['status'] ?? BlogDetail::PUBLISHED])->save();
            } elseif ($audit->applied_action === 'noindex') {
                $blog->forceFill(['noindex' => (bool) ($s['noindex'] ?? false)])->save();
            }

            $audit->update([
                'applied_action' => null, 'applied_at' => null, 'applied_snapshot' => null, 'undone_at' => now(),
                'decision' => null, 'decision_target_id' => null, 'decided_by' => null, 'decided_at' => null,
            ]);
            Log::info('Article audit step undone', ['by' => $by->email, 'article' => $blog->id]);
        });
        SeoAudit::flush();
    }
}
