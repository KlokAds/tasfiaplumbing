<?php

namespace App\Support\Google;

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * The period a report covers (7 days, 28 days, 3 months, 12 months or custom dates)
 * and the period of the same length right before it, for the comparison.
 */
class ReportRange
{
    public const PRESETS = [
        '7d' => ['label' => 'Last 7 days', 'days' => 7],
        '28d' => ['label' => 'Last 28 days', 'days' => 28],
        '3m' => ['label' => 'Last 3 months', 'days' => 91],
        '12m' => ['label' => 'Last 12 months', 'days' => 365],
    ];

    public const DEFAULT = '28d';

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

        $days = self::PRESETS[$key]['days'] ?? self::PRESETS[self::DEFAULT]['days'];
        $key = isset(self::PRESETS[$key]) ? $key : self::DEFAULT;

        return new self($key, $latest->copy()->subDays($days - 1), $latest->copy());
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
