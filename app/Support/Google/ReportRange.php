<?php

namespace App\Support\Google;

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * The period a report covers (today, yesterday, 3 days … 1 year, or custom dates) and the period
 * of the same length right before it, for the comparison.
 *
 * "today" and "yesterday" end on that calendar day (Google's data for them is still filling in);
 * the other presets end on the latest day the source has complete data for.
 */
class ReportRange
{
    public const PRESETS = [
        'today' => ['label' => 'Today', 'days' => 1, 'ends_days_ago' => 0],
        'yesterday' => ['label' => 'Yesterday', 'days' => 1, 'ends_days_ago' => 1],
        '3d' => ['label' => '3 days', 'days' => 3],
        '7d' => ['label' => '7 days', 'days' => 7],
        '15d' => ['label' => '15 days', 'days' => 15],
        '30d' => ['label' => '30 days', 'days' => 30],
        '3m' => ['label' => '3 months', 'days' => 91],
        '6m' => ['label' => '6 months', 'days' => 182],
        '12m' => ['label' => '1 year', 'days' => 365],
    ];

    public const DEFAULT = '30d';

    private function __construct(
        public readonly string $key,
        public readonly Carbon $start,
        public readonly Carbon $end,
    ) {}

    /**
     * @param int $lagDays how far behind the source is (Search Console: 2 days, Analytics: 1 day)
     * @param int $maxMonths how far back the source keeps data
     */
    public static function fromRequest(Request $request, int $lagDays, int $maxMonths): self
    {
        return self::make((string) $request->input('range', self::DEFAULT), $request->input('from'), $request->input('to'), $lagDays, $maxMonths);
    }

    public static function make(string $key, ?string $from, ?string $to, int $lagDays, int $maxMonths): self
    {
        $latest = now()->subDays($lagDays)->startOfDay();
        $earliest = now()->subMonths($maxMonths)->startOfDay();

        if ($key === 'custom') {
            try {
                $start = Carbon::parse((string) $from)->startOfDay();
                $end = Carbon::parse((string) $to)->startOfDay();
            } catch (\Throwable) {
                return self::make(self::DEFAULT, null, null, $lagDays, $maxMonths);
            }
            if ($end->gt($latest)) {
                $end = $latest->copy();
            }
            if ($start->lt($earliest)) {
                $start = $earliest->copy();
            }
            if ($start->gt($end)) {
                [$start, $end] = [$end->copy(), $start->copy()];
            }

            return new self('custom', $start, $end);
        }

        $key = isset(self::PRESETS[$key]) ? $key : self::DEFAULT;
        $days = self::PRESETS[$key]['days'];
        $end = isset(self::PRESETS[$key]['ends_days_ago']) ? now()->subDays(self::PRESETS[$key]['ends_days_ago'])->startOfDay() : $latest->copy();

        return new self($key, $end->copy()->subDays($days - 1), $end);
    }

    /** The period reaches days Google is still filling in (ask for fresh, not only final, data). */
    public function recent(int $lagDays): bool
    {
        return $this->end->gt(now()->subDays($lagDays)->startOfDay());
    }

    public function days(): int
    {
        return (int) $this->start->diffInDays($this->end) + 1;
    }

    public function previousStart(): Carbon
    {
        return $this->start->copy()->subDays($this->days());
    }

    public function previousEnd(): Carbon
    {
        return $this->start->copy()->subDay();
    }

    /** Long periods are charted per week, so the line stays readable. */
    public function weekly(): bool
    {
        return $this->days() > 92;
    }

    public function cacheSuffix(): string
    {
        return $this->key === self::DEFAULT ? '' : '.' . $this->start->toDateString() . '_' . $this->end->toDateString();
    }

    public function label(): string
    {
        return self::PRESETS[$this->key]['label'] ?? 'Custom dates';
    }

    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'label' => $this->label(),
            'start' => $this->start->toDateString(),
            'end' => $this->end->toDateString(),
            'previous_start' => $this->previousStart()->toDateString(),
            'previous_end' => $this->previousEnd()->toDateString(),
            'days' => $this->days(),
            'weekly' => $this->weekly(),
        ];
    }

    /** Every day of the period, with 0 for days Google sent nothing for (so the chart has no gaps). */
    public function fillDays(array $daily, array $fields): array
    {
        $byDate = [];
        foreach ($daily as $row) {
            $byDate[$row['date']] = $row;
        }
        $out = [];
        for ($d = $this->start->copy(); $d->lte($this->end); $d->addDay()) {
            $key = $d->toDateString();
            $out[] = $byDate[$key] ?? ['date' => $key] + array_fill_keys($fields, 0);
        }

        return $out;
    }

    /** Sums daily rows into weeks starting on Monday: [date => Y-m-d, ...numbers]. */
    public static function toWeeks(array $daily, array $fields): array
    {
        $weeks = [];
        foreach ($daily as $row) {
            $week = Carbon::parse($row['date'])->startOfWeek()->toDateString();
            $weeks[$week] ??= ['date' => $week] + array_fill_keys($fields, 0);
            foreach ($fields as $f) {
                $weeks[$week][$f] += $row[$f] ?? 0;
            }
        }
        ksort($weeks);

        return array_values($weeks);
    }
}
