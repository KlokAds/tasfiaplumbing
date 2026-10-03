<?php

namespace App\Support;

use App\Models\ArticleRevision;
use App\Models\BlogDetail;
use App\Models\SiteSetting;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Source check: are sentences of an article already on other websites? A few distinctive
 * sentences are searched as exact phrases with the Brave Search API. Copied text shows up
 * as other pages with the same sentence.
 *
 * Runs from the scheduler (articles:source-check, every minute) for articles and changes that
 * wait for approval, one search per second (the free Brave plan allows 1 per second). The
 * API key is saved encrypted in site settings from the Articles page (Super Admin only).
 */
class SourceCheck
{
    public const KEY_SETTING = 'source_check.key';

    private const ENDPOINT = 'https://api.search.brave.com/res/v1/web/search';

    /** Sentences searched per article. */
    public const SENTENCES = 6;

    /** Items checked per scheduler run (each takes about SENTENCES seconds). */
    private const PER_RUN = 3;

    /**
     * Searches per month on this site. The Brave plan includes $5 of credit a month (1,000 searches);
     * 4 sites x 240 stays inside it, so the card is never charged. A Super Admin can change it in
     * System → API keys. The count is kept in the database (not the cache), per calendar month.
     */
    public const LIMIT_SETTING = 'source_check.monthly_limit';

    public const DEFAULT_LIMIT = 240;

    /** Pause between searches in microseconds (tests set it to 0). */
    public static int $pause = 1_100_000;

    public static function key(): ?string
    {
        $stored = SiteSetting::stored(self::KEY_SETTING);
        if (!$stored) {
            return null;
        }
        try {
            return Crypt::decryptString($stored);
        } catch (\Throwable) {
            return null; // APP_KEY changed: the key has to be saved again
        }
    }

    public static function enabled(): bool
    {
        return filled(self::key());
    }

    public static function monthlyLimit(): int
    {
        return max(0, (int) SiteSetting::stored(self::LIMIT_SETTING, self::DEFAULT_LIMIT));
    }

    public static function used(): int
    {
        return (int) SiteSetting::where('key', self::usedKey())->value('value');
    }

    public static function left(): int
    {
        return max(0, self::monthlyLimit() - self::used());
    }

    /** used / limit this month, for the API keys tab and the Articles page. */
    public static function usage(): array
    {
        return ['used' => self::used(), 'limit' => self::monthlyLimit(), 'per_article' => self::SENTENCES];
    }

    private static function usedKey(): string
    {
        return 'source_check.used.' . now()->timezone(config('admin.timezone'))->format('Y-m');
    }

    private static function countSearch(): void
    {
        $row = SiteSetting::firstOrCreate(['key' => self::usedKey()], ['value' => '0']);
        $row->update(['value' => (string) ((int) $row->value + 1)]);
    }

    /** What the Articles page shows: on/off, this month's searches and, for a Super Admin, the last 4 characters. */
    public static function status(bool $manager): array
    {
        $key = self::key();

        return [
            'on' => filled($key),
            'usage' => self::usage(),
            'last4' => $manager && $key ? substr($key, -4) : null,
            'can_manage' => $manager,
        ];
    }

    public static function saveKey(?string $key): void
    {
        SiteSetting::putMany([self::KEY_SETTING => filled($key) ? Crypt::encryptString(trim($key)) : '']);
    }

    /** One search to see that the key works. Returns null when it does, or the problem. */
    public static function testKey(string $key): ?string
    {
        if (self::left() < 1) {
            return null; // no searches left this month: the key is saved untested
        }
        try {
            $res = self::request($key, '"door closer"', 1);
        } catch (\Throwable $e) {
            return 'Brave Search could not be reached. Try again in a minute.';
        }
        if (in_array($res->status(), [401, 403, 422], true)) {
            return 'Brave Search did not accept this key. Copy it again from the Brave dashboard (API keys).';
        }
        if ($res->status() === 429) {
            return null; // the key is valid, only the limit was hit
        }

        return $res->successful() ? null : 'Brave Search answered with an error (' . $res->status() . '). Try again in a minute.';
    }

    /**
     * Distinctive sentences from the article text: long enough to be unique (10+ words),
     * spread over the whole article, no headings, questions or list labels.
     *
     * @return list<string>
     */
    public static function sentences(string $html, int $count = self::SENTENCES): array
    {
        $text = preg_replace('#<(h[1-6])[^>]*>.*?</\1>#is', "\n", $html);
        $text = preg_replace('#</(p|li|tr|blockquote|div)>|<br\s*/?>#i', "\n", (string) $text);
        $text = html_entity_decode(strip_tags((string) $text), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        $pool = [];
        foreach (preg_split('/\n+/', $text) as $block) {
            foreach (preg_split('/(?<=[.!?])\s+(?=[A-Z0-9“"(])/u', trim(preg_replace('/\s+/u', ' ', $block))) as $s) {
                $s = trim($s, " \t\"“”");
                if (str_word_count($s) >= 10 && !str_ends_with($s, '?') && !str_ends_with($s, ':')) {
                    // A search phrase of at most 22 words (search engines cut long quotes).
                    $pool[] = implode(' ', array_slice(preg_split('/\s+/u', $s), 0, 22));
                }
            }
        }
        $pool = array_values(array_unique($pool));
        if (count($pool) <= $count) {
            return $pool;
        }

        // Evenly spread: the intro, the middle and the end all get checked.
        $picked = [];
        for ($i = 0; $i < $count; $i++) {
            $picked[] = $pool[(int) floor($i * count($pool) / $count)];
        }

        return $picked;
    }

    /** A fingerprint of the text, so a changed text is checked again. */
    public static function hash(string $html): string
    {
        return md5(implode('|', self::sentences($html, 500)));
    }

    /**
     * Search the sentences. Returns the result stored on the article or change.
     *
     * @return array{checked_at: string, hash: string, total: int, found: int, matches: list<array{sentence: string, urls: list<string>}>, error?: string}
     */
    public static function run(string $html, ?string $key = null): array
    {
        $key ??= (string) self::key();
        $sentences = self::sentences($html);
        $own = self::host((string) config('app.url'));

        $matches = [];
        $error = null;
        foreach ($sentences as $i => $sentence) {
            if ($i > 0 && self::$pause > 0) {
                usleep(self::$pause); // 1 search per second
            }
            if (self::left() < 1) {
                $error = 'The monthly limit of ' . self::monthlyLimit() . ' searches is used up. Checks start again next month.';
                break;
            }
            try {
                $res = self::request($key, '"' . str_replace('"', '', $sentence) . '"', 5);
            } catch (\Throwable) {
                $error = 'Brave Search could not be reached. It is checked again later.';
                break;
            }
            if (!$res->successful()) {
                $error = match ($res->status()) {
                    401, 403, 422 => 'The Brave Search key no longer works. A Super Admin can save a new one in System → API keys.',
                    429 => 'The Brave Search limit was reached. It is checked again later.',
                    default => 'Brave Search answered with an error (' . $res->status() . '). It is checked again later.',
                };
                break;
            }
            $urls = collect($res->json('web.results', []))->pluck('url')->filter()
                ->reject(fn ($u) => $own && self::host((string) $u) === $own)
                ->take(3)->values()->all();
            if ($urls) {
                $matches[] = ['sentence' => $sentence, 'urls' => $urls];
            }
        }

        return array_filter([
            'checked_at' => now()->toIso8601String(),
            'hash' => self::hash($html),
            'total' => count($sentences),
            'found' => count($matches),
            'matches' => $matches,
            'error' => $error,
        ], fn ($v) => $v !== null);
    }

    /**
     * The scheduled run: articles and changes waiting for approval that were not checked
     * yet, or whose text changed since. Returns how many were checked.
     */
    public static function due(): int
    {
        $key = self::key();
        if (!$key || self::left() < self::SENTENCES) {
            return 0; // off, or this month's searches are used up (items stay "waiting")
        }

        $articles = BlogDetail::where('status', BlogDetail::PENDING)->orderBy('updated_at')->get();
        $changes = ArticleRevision::where('status', 'pending')->orderBy('updated_at')->get();
        $items = $articles->map(fn ($b) => [$b, (string) $b->desc])
            ->concat($changes->map(fn ($r) => [$r, (string) ($r->payload['desc'] ?? '')]));

        $done = 0;
        foreach ($items as [$model, $html]) {
            if ($done >= self::PER_RUN) {
                break;
            }
            $last = $model->source_check;
            $failedLongAgo = !empty($last['error']) && now()->subMinutes(30)->gte($last['checked_at'] ?? '2000-01-01');
            if (($last && ($last['hash'] ?? null) === self::hash($html) && !$failedLongAgo) || !self::sentences($html)) {
                continue;
            }

            $result = self::run($html, $key);
            $model->forceFill(['source_check' => $result])->saveQuietly();
            $done++;
            if (!empty($result['error'])) {
                Log::warning('Source check stopped', ['error' => $result['error']]);
                break; // a key or limit problem: wait for the next run
            }
            if ($result['found'] > 0 && empty($last['found'])) {
                ArticleNotifier::sourceFound($model, $result);
            }
        }

        return $done;
    }

    private static function host(string $url): ?string
    {
        $host = parse_url($url, PHP_URL_HOST);

        return $host ? Str::lower(preg_replace('/^www\./i', '', $host)) : null;
    }

    private static function request(string $key, string $query, int $count)
    {
        self::countSearch();

        return Http::withHeaders(['Accept' => 'application/json', 'X-Subscription-Token' => $key])
            ->timeout(15)
            ->get(self::ENDPOINT, ['q' => $query, 'count' => $count, 'safesearch' => 'off', 'spellcheck' => 0]);
    }
}
