<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SiteEvent;
use App\Models\SiteSetting;
use App\Support\VisitorStats;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;

/** Insights → Visitor counter: the website's own count of real visitors, countries and contact clicks. */
class VisitorController extends Controller
{
    public const PERIODS = [
        'today' => 'Today', 'yesterday' => 'Yesterday', '7d' => '7 days', '30d' => '30 days',
        '3m' => '3 months', '6m' => '6 months', '12m' => '1 year', 'custom' => 'Custom',
    ];

    public function index(Request $request)
    {
        [$key, $from, $to] = self::period($request);
        $first = SiteEvent::min('day');

        return Inertia::render('Admin/Insights/Visitors', [
            'period' => ['key' => $key, 'from' => $from->toDateString(), 'to' => $to->toDateString()],
            'periods' => self::PERIODS,
            'stats' => VisitorStats::summary($from, $to),
            'labels' => VisitorStats::LABELS,
            'countingSince' => $first ? Carbon::parse($first)->toDateString() : null,
            'dailyEmail' => SiteSetting::stored('visitors.daily_email', '1') === '1',
            'timezone' => config('admin.timezone_label'),
        ]);
    }

    /** The 6 pm summary email on or off. */
    public function dailyEmail(Request $request)
    {
        $on = $request->validate(['on' => 'required|boolean'])['on'];
        SiteSetting::putMany(['visitors.daily_email' => $on ? '1' : '0']);

        return back()->with('success', $on ? 'The visitor summary is emailed every day at 6 pm.' : 'The daily visitor email is off.');
    }

    /** @return array{0: string, 1: Carbon, 2: Carbon} */
    public static function period(Request $request): array
    {
        $today = now(config('admin.timezone'))->startOfDay();
        $key = array_key_exists($request->input('range'), self::PERIODS) ? $request->input('range') : '30d';
        if ($key === 'custom') {
            try {
                $from = Carbon::parse((string) $request->input('from'), config('admin.timezone'))->startOfDay();
                $to = Carbon::parse((string) $request->input('to'), config('admin.timezone'))->startOfDay();
            } catch (\Throwable) {
                [$key, $from, $to] = ['30d', $today->copy()->subDays(29), $today->copy()];
            }
            if (isset($from, $to) && $from->gt($to)) {
                [$from, $to] = [$to, $from];
            }
            if (isset($from) && $from->diffInDays($to) > 400) {
                $from = $to->copy()->subDays(400);
            }

            return [$key, $from, $to->gt($today) ? $today->copy() : $to];
        }

        return match ($key) {
            'today' => [$key, $today->copy(), $today->copy()],
            'yesterday' => [$key, $today->copy()->subDay(), $today->copy()->subDay()],
            '7d' => [$key, $today->copy()->subDays(6), $today->copy()],
            '3m' => [$key, $today->copy()->subDays(90), $today->copy()],
            '6m' => [$key, $today->copy()->subDays(181), $today->copy()],
            '12m' => [$key, $today->copy()->subDays(364), $today->copy()],
            default => ['30d', $today->copy()->subDays(29), $today->copy()],
        };
    }
}
