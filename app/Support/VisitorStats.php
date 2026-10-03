<?php

namespace App\Support;

use App\Models\SiteEvent;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * The website's own visitor counter: real people only (the signal is sent by the page's script,
 * so most bots never send it; known bot names and signed-in admins are left out too).
 *
 * - visitor: a daily one-way hash of the IP address and browser, so the same person is counted
 *   once per day; the IP itself is never stored;
 * - country: from the visitor's device time zone (Asia/Dhaka → BD), because the hosting does not
 *   say where an IP is from and no IP leaves the server;
 * - days follow the business time zone (config admin.timezone).
 */
class VisitorStats
{
    public const LABELS = [
        'visit' => 'Page views',
        'whatsapp' => 'WhatsApp clicks',
        'call' => 'Call clicks',
        'chat' => 'Chats started',
        'chat_message' => 'Chats with a message', // the visitor wrote at least one message
    ];

    private const BOTS = '/bot|crawl|spider|slurp|preview|lighthouse|headless|phantom|python|curl|wget|httpclient|monitor|pingdom|uptime|facebookexternalhit|whatsapp\/|scan/i';

    /** Saves one signal from the website. Returns false when it is not counted (bot, admin, bad data). */
    public static function record(Request $request, string $type, ?string $path, ?string $timezone, ?string $referrer, ?int $width): bool
    {
        $agent = (string) $request->userAgent();
        if (!in_array($type, SiteEvent::TYPES, true) || $agent === '' || preg_match(self::BOTS, $agent) || $request->user()) {
            return false;
        }

        $now = now(config('admin.timezone'));
        $path = $path ? Str::limit('/' . ltrim((string) parse_url($path, PHP_URL_PATH), '/'), 290, '') : null;
        if ($path && str_starts_with($path, '/admin')) {
            return false;
        }
        $source = $referrer ? strtolower((string) parse_url($referrer, PHP_URL_HOST)) : null;
        $own = strtolower((string) parse_url((string) config('app.url'), PHP_URL_HOST));
        if ($source && (preg_replace('/^www\./', '', $source) === preg_replace('/^www\./', '', $own))) {
            $source = null; // moving inside the site is not a source
        }

        SiteEvent::create([
            'day' => $now->toDateString(),
            'type' => $type,
            'visitor' => substr(hash_hmac('sha256', $request->ip() . '|' . $agent . '|' . $now->toDateString(), (string) config('app.key')), 0, 16),
            'country' => self::countryFromTimezone($timezone),
            'path' => $path,
            'source' => $source ? Str::limit(preg_replace('/^www\./', '', $source), 100, '') : null,
            'device' => ($width && $width < 768) || preg_match('/Mobi|Android|iPhone/i', $agent) ? 'mobile' : 'desktop',
            'created_at' => now(),
        ]);

        return true;
    }

    /**
     * The 6 pm email: today so far and yesterday, to every active user who may see the counter
     * (Super Admins and roles with "Visitor counter: View"). Returns how many were emailed.
     */
    public static function sendDaily(): int
    {
        if (\App\Models\SiteSetting::stored('visitors.daily_email', '1') !== '1') {
            return 0;
        }
        $today = now(config('admin.timezone'))->startOfDay();
        $summary = self::summary($today->copy(), $today->copy());
        $yesterday = self::summary($today->copy()->subDay(), $today->copy()->subDay())['totals'];

        $sent = 0;
        foreach (\App\Models\User::where('is_active', true)->whereNotNull('email')->get() as $user) {
            if (!$user->can('visitors.view')) {
                continue;
            }
            try {
                $user->notify(new \App\Notifications\DailyVisitorReport($today->format('D j M Y'), $summary, $yesterday));
                $sent++;
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('Daily visitor email failed', ['user' => $user->id, 'error' => $e->getMessage()]);
            }
        }

        return $sent;
    }

    public static function countryFromTimezone(?string $timezone): ?string
    {
        if (!$timezone || !in_array($timezone, \DateTimeZone::listIdentifiers(), true)) {
            return null;
        }
        $code = (new \DateTimeZone($timezone))->getLocation()['country_code'] ?? null;

        return $code && preg_match('/^[A-Z]{2}$/', $code) && $code !== '??' ? $code : null;
    }

    public static function countryName(?string $code): string
    {
        if (!$code) {
            return 'Unknown';
        }

        return class_exists(\Locale::class) ? (\Locale::getDisplayRegion('-' . $code, 'en') ?: $code) : $code;
    }

    /**
     * Totals, a row per day, countries, pages and sources for a period (days in the business time zone).
     *
     * @return array{totals: array, days: list<array>, countries: list<array>, pages: list<array>, sources: list<array>, devices: array}
     */
    public static function summary(Carbon $from, Carbon $to): array
    {
        $scope = fn () => SiteEvent::query()->whereBetween('day', [$from->toDateString(), $to->toDateString()]);

        $totals = ['visitors' => (clone $scope())->where('type', 'visit')->distinct()->count('visitor')];
        foreach (SiteEvent::TYPES as $type) {
            $totals[$type] = 0;
        }
        foreach ((clone $scope())->selectRaw('type, count(*) as n')->groupBy('type')->pluck('n', 'type') as $type => $n) {
            $totals[$type] = (int) $n;
        }

        $byDay = (clone $scope())->selectRaw('day, type, count(*) as n, count(distinct visitor) as people')->groupBy('day', 'type')->get();
        $days = [];
        for ($d = $to->copy(); $d->gte($from); $d->subDay()) {
            $key = $d->toDateString();
            $row = ['day' => $key, 'visitors' => 0] + array_fill_keys(SiteEvent::TYPES, 0);
            foreach ($byDay->filter(fn ($r) => Carbon::parse($r->day)->toDateString() === $key) as $r) {
                $row[$r->type] = (int) $r->n;
                if ($r->type === 'visit') {
                    $row['visitors'] = (int) $r->people;
                }
            }
            $days[] = $row;
        }

        $countries = (clone $scope())->where('type', 'visit')
            ->selectRaw('country, count(distinct visitor) as visitors, count(*) as views')
            ->groupBy('country')->orderByDesc('visitors')->limit(20)->get()
            ->map(fn ($r) => ['code' => $r->country, 'name' => self::countryName($r->country), 'visitors' => (int) $r->visitors, 'views' => (int) $r->views])->all();
        $leadsByCountry = (clone $scope())->whereIn('type', ['whatsapp', 'call', 'chat'])
            ->selectRaw('country, count(*) as n')->groupBy('country')->pluck('n', 'country');
        foreach ($countries as &$c) {
            $c['contacts'] = (int) ($leadsByCountry[$c['code']] ?? 0);
        }
        unset($c);

        $pages = (clone $scope())->where('type', 'visit')->whereNotNull('path')
            ->selectRaw('path, count(*) as views, count(distinct visitor) as visitors')
            ->groupBy('path')->orderByDesc('views')->limit(15)->get()
            ->map(fn ($r) => ['path' => $r->path, 'views' => (int) $r->views, 'visitors' => (int) $r->visitors])->all();
        $contactPages = (clone $scope())->whereIn('type', ['whatsapp', 'call', 'chat'])->whereNotNull('path')
            ->selectRaw('path, count(*) as n')->groupBy('path')->orderByDesc('n')->limit(10)->get()
            ->map(fn ($r) => ['path' => $r->path, 'contacts' => (int) $r->n])->all();

        $sources = (clone $scope())->where('type', 'visit')
            ->selectRaw("COALESCE(source, '') as source, count(distinct visitor) as visitors")
            ->groupBy(DB::raw("COALESCE(source, '')"))->orderByDesc('visitors')->limit(10)->get()
            ->map(fn ($r) => ['source' => $r->source ?: 'Direct / unknown', 'visitors' => (int) $r->visitors])->all();

        $devices = (clone $scope())->where('type', 'visit')->selectRaw('device, count(distinct visitor) as n')->groupBy('device')->pluck('n', 'device')->all();

        return compact('totals', 'days', 'countries', 'pages', 'contactPages', 'sources', 'devices');
    }
}
