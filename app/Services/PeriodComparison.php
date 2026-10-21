<?php

namespace App\Services;

use App\Models\BusinessLine;
use App\Models\Car;
use App\Models\Farm;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Two periods side by side for the QA Monitor: figures for each side (same definitions as the
 * dashboard), the change between them, and a few plain-language highlights. Used by the
 * comparison page and its CSV export, so both always agree.
 *
 * A side is ['year' => 2026, 'period' => 'year'|'h1'|'h2'|'q1'..'q4'|'m01'..'m12'|'custom',
 * 'from' => ?Y-m-d, 'to' => ?Y-m-d, 'line' => ?int, 'farm' => ?int].
 */
class PeriodComparison
{
    /**
     * @var array<string, string>
     */
    public const PERIODS = [
        'year' => 'Full year',
        'h1' => 'First half (Jan–Jun)',
        'h2' => 'Second half (Jul–Dec)',
        'q1' => 'Q1 (Jan–Mar)',
        'q2' => 'Q2 (Apr–Jun)',
        'q3' => 'Q3 (Jul–Sep)',
        'q4' => 'Q4 (Oct–Dec)',
        'm01' => 'January', 'm02' => 'February', 'm03' => 'March', 'm04' => 'April',
        'm05' => 'May', 'm06' => 'June', 'm07' => 'July', 'm08' => 'August',
        'm09' => 'September', 'm10' => 'October', 'm11' => 'November', 'm12' => 'December',
        'custom' => 'Custom dates',
    ];

    /**
     * The headline rows: key => [label, which way is better, unit].
     *
     * @var array<string, array{0: string, 1: 'lower'|'higher', 2: 'count'|'percent'|'days'}>
     */
    public const ROWS = [
        'issued' => ['CARs issued', 'lower', 'count'],
        'closed' => ['Closed', 'higher', 'count'],
        'closure_rate' => ['Closure rate', 'higher', 'percent'],
        'still_open' => ['Still open', 'lower', 'count'],
        'avg_response_days' => ['Avg. response time', 'lower', 'days'],
        'on_time_response_rate' => ['On-time response', 'higher', 'percent'],
        'avg_resolution_days' => ['Avg. resolution time', 'lower', 'days'],
    ];

    private CarbonImmutable $today;

    /**
     * @param  array{year: int, period: string, from?: ?string, to?: ?string, line?: ?int, farm?: ?int}  $left
     * @param  array{year: int, period: string, from?: ?string, to?: ?string, line?: ?int, farm?: ?int}  $right
     * @param  bool  $sameFilters  The right side uses the left side's business line and farm.
     * @param  bool  $sameSpan  When a period runs past today, both sides are cut to the same span.
     */
    public function __construct(
        private array $left,
        private array $right,
        private bool $sameFilters = true,
        private bool $sameSpan = true,
        ?CarbonImmutable $today = null,
    ) {
        $this->today = $today ?? CarbonImmutable::today();

        if ($this->sameFilters) {
            $this->right['line'] = $this->left['line'] ?? null;
            $this->right['farm'] = $this->left['farm'] ?? null;
        }
    }

    /**
     * @return array{
     *     left: array{label: string, range: string, filters: string, figures: array<string, mixed>},
     *     right: array{label: string, range: string, filters: string, figures: array<string, mixed>},
     *     rows: list<array{key: string, label: string, left: int|float|null, right: int|float|null, left_text: string, right_text: string, change: ?string, tone: string}>,
     *     months: list<array{left: ?array{label: string, count: int}, right: ?array{label: string, count: int}}>,
     *     breakdowns: array<string, array{title: string, rows: list<array{name: string, detail: ?string, left: int, right: int, change: ?string, tone: string}>}>,
     *     highlights: list<string>,
     *     cut: bool
     * }
     */
    public function build(): array
    {
        [$leftFrom, $leftTo] = $this->range($this->left);
        [$rightFrom, $rightTo] = $this->range($this->right);
        $cut = false;

        if ($this->sameSpan) {
            [$leftFrom, $leftTo, $rightFrom, $rightTo, $cut] = $this->matchSpans($leftFrom, $leftTo, $rightFrom, $rightTo);
        }

        $leftFigures = DashboardMetrics::forRange($leftFrom, $leftTo, $this->left['line'] ?? null, $this->left['farm'] ?? null, $this->today)->periodFigures();
        $rightFigures = DashboardMetrics::forRange($rightFrom, $rightTo, $this->right['line'] ?? null, $this->right['farm'] ?? null, $this->today)->periodFigures();

        $rows = collect(self::ROWS)->map(fn (array $row, string $key): array => [
            'key' => $key,
            'label' => $row[0],
            'left' => $leftFigures[$key],
            'right' => $rightFigures[$key],
            'left_text' => $this->format($leftFigures[$key], $row[2]),
            'right_text' => $this->format($rightFigures[$key], $row[2]),
            ...$this->change($leftFigures[$key], $rightFigures[$key], $row[1], $row[2]),
        ])->values()->all();

        return [
            'left' => $this->side($this->left, $leftFrom, $leftTo, $leftFigures),
            'right' => $this->side($this->right, $rightFrom, $rightTo, $rightFigures),
            'rows' => $rows,
            'months' => $this->pairMonths($leftFigures['months'], $rightFigures['months']),
            'breakdowns' => [
                'subcategories' => ['title' => 'By sub-category', 'rows' => $this->breakdown(
                    collect($leftFigures['subcategories'])->mapWithKeys(fn (array $row): array => [$row['subcategory'].' — '.$row['category'] => $row]),
                    collect($rightFigures['subcategories'])->mapWithKeys(fn (array $row): array => [$row['subcategory'].' — '.$row['category'] => $row]),
                    limit: 10,
                )],
                'categories' => ['title' => 'By category', 'rows' => $this->breakdown(collect($leftFigures['categories']), collect($rightFigures['categories']))],
                'units' => ['title' => 'By unit', 'rows' => $this->breakdown(collect($leftFigures['units']), collect($rightFigures['units']))],
                'farms' => ['title' => 'By farm', 'rows' => $this->breakdown(collect($leftFigures['farms']), collect($rightFigures['farms']))],
            ],
            'highlights' => $this->highlights($rows, $leftFigures, $rightFigures),
            'cut' => $cut,
        ];
    }

    /**
     * Years that have CARs, plus the current year, newest first.
     *
     * @return list<int>
     */
    public static function years(?CarbonImmutable $today = null): array
    {
        $today ??= CarbonImmutable::today();
        $first = Car::query()->min('issued_on');
        $firstYear = $first ? min((int) substr((string) $first, 0, 4), $today->year - 1) : $today->year - 1;

        return range($today->year, $firstYear);
    }

    /**
     * @param  array{year: int, period: string, from?: ?string, to?: ?string}  $side
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    private function range(array $side): array
    {
        $year = CarbonImmutable::create((int) $side['year'], 1, 1);
        $period = $side['period'] ?? 'year';

        if ($period === 'custom') {
            $from = rescue(fn () => CarbonImmutable::parse((string) $side['from']), $year, report: false);
            $to = rescue(fn () => CarbonImmutable::parse((string) $side['to']), $year->endOfYear(), report: false);

            return $from->lte($to) ? [$from->startOfDay(), $to->startOfDay()] : [$to->startOfDay(), $from->startOfDay()];
        }

        return match (true) {
            $period === 'h1' => [$year, $year->month(6)->endOfMonth()],
            $period === 'h2' => [$year->month(7), $year->endOfYear()],
            in_array($period, ['q1', 'q2', 'q3', 'q4'], true) => [
                $year->month(((int) $period[1] - 1) * 3 + 1),
                $year->month((int) $period[1] * 3)->endOfMonth(),
            ],
            str_starts_with($period, 'm') => [$year->month((int) substr($period, 1)), $year->month((int) substr($period, 1))->endOfMonth()],
            default => [$year, $year->endOfYear()],
        };
    }

    /**
     * When a side runs past today (an unfinished period), cut it at today and cut the other side to
     * the same number of days, so a partial year is never compared with a whole one.
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable, 2: CarbonImmutable, 3: CarbonImmutable, 4: bool}
     */
    private function matchSpans(CarbonImmutable $leftFrom, CarbonImmutable $leftTo, CarbonImmutable $rightFrom, CarbonImmutable $rightTo): array
    {
        $cut = false;

        if ($rightTo->gt($this->today) && $rightFrom->lte($this->today)) {
            $leftTo = $leftTo->min($leftFrom->addDays((int) $rightFrom->diffInDays($this->today)));
            $rightTo = $this->today;
            $cut = true;
        }

        if ($leftTo->gt($this->today) && $leftFrom->lte($this->today)) {
            $rightTo = $rightTo->min($rightFrom->addDays((int) $leftFrom->diffInDays($this->today)));
            $leftTo = $this->today;
            $cut = true;
        }

        return [$leftFrom, $leftTo, $rightFrom, $rightTo, $cut];
    }

    /**
     * @param  array{year: int, period: string, line?: ?int, farm?: ?int}  $side
     * @param  array<string, mixed>  $figures
     * @return array{label: string, range: string, filters: string, figures: array<string, mixed>}
     */
    private function side(array $side, CarbonImmutable $from, CarbonImmutable $to, array $figures): array
    {
        $period = $side['period'] ?? 'year';

        return [
            'label' => match (true) {
                $period === 'custom' => $from->format('M j, Y').' – '.$to->format('M j, Y'),
                $period === 'year' => (string) $side['year'],
                default => str(self::PERIODS[$period] ?? '')->before(' (')->append(' '.$side['year'])->toString(),
            },
            'range' => $from->format('M j, Y').' – '.$to->format('M j, Y'),
            'filters' => collect([
                BusinessLine::find($side['line'] ?? null)?->name ?? 'All business lines',
                Farm::find($side['farm'] ?? null)?->name ?? 'All farms',
            ])->join(' · '),
            'figures' => $figures,
        ];
    }

    private function format(int|float|null $value, string $unit): string
    {
        return $value === null ? '—' : $value.match ($unit) {
            'percent' => '%',
            'days' => ' d',
            default => '',
        };
    }

    /**
     * The change from left to right, and whether it is an improvement.
     *
     * @return array{change: ?string, tone: string}
     */
    private function change(int|float|null $left, int|float|null $right, string $better, string $unit): array
    {
        if ($left === null || $right === null) {
            return ['change' => null, 'tone' => 'neutral'];
        }

        $diff = round($right - $left, 1);

        if ($diff == 0) {
            return ['change' => 'no change', 'tone' => 'neutral'];
        }

        $arrow = $diff > 0 ? '▲' : '▼';
        $amount = abs($diff);
        $text = match ($unit) {
            'percent' => "{$arrow} {$amount} pts",
            'days' => "{$arrow} {$amount} d",
            default => "{$arrow} {$amount}".($left > 0 ? ' ('.($diff > 0 ? '+' : '−').round(abs($diff) / $left * 100).'%)' : ''),
        };

        return ['change' => $text, 'tone' => ($diff < 0) === ($better === 'lower') ? 'better' : 'worse'];
    }

    /**
     * Rows for a breakdown table: every name seen on either side, most frequent first. More CARs is
     * worse in every breakdown.
     *
     * @param  Collection<string, int|array{count: int, line?: string}>  $left
     * @param  Collection<string, int|array{count: int, line?: string}>  $right
     * @return list<array{name: string, detail: ?string, left: int, right: int, change: ?string, tone: string}>
     */
    private function breakdown(Collection $left, Collection $right, ?int $limit = null): array
    {
        $count = fn (mixed $value): int => is_array($value) ? $value['count'] : (int) $value;

        return $left->keys()->merge($right->keys())->unique()
            ->map(function (string $name) use ($left, $right, $count): array {
                $leftCount = $count($left[$name] ?? 0);
                $rightCount = $count($right[$name] ?? 0);
                $detail = is_array($right[$name] ?? null) ? $right[$name]['line'] : (is_array($left[$name] ?? null) ? $left[$name]['line'] : null);

                return ['name' => $name, 'detail' => $detail, 'left' => $leftCount, 'right' => $rightCount, ...$this->change($leftCount, $rightCount, 'lower', 'count')];
            })
            ->sortByDesc(fn (array $row): int => max($row['left'], $row['right']) * 1000 + $row['right'])
            ->when($limit, fn (Collection $rows) => $rows->take($limit))
            ->values()
            ->all();
    }

    /**
     * Months side by side by position (1st month with 1st month), so periods of the same length line up.
     *
     * @param  list<array{label: string, count: int}>  $left
     * @param  list<array{label: string, count: int}>  $right
     * @return list<array{left: ?array{label: string, count: int}, right: ?array{label: string, count: int}}>
     */
    private function pairMonths(array $left, array $right): array
    {
        return collect(range(0, max(count($left), count($right)) - 1))
            ->map(fn (int $i): array => ['left' => $left[$i] ?? null, 'right' => $right[$i] ?? null])
            ->all();
    }

    /**
     * Up to three plain sentences: the change in volume, the biggest mover, and response or resolution.
     *
     * @param  list<array{key: string, left: int|float|null, right: int|float|null}>  $rows
     * @param  array<string, mixed>  $leftFigures
     * @param  array<string, mixed>  $rightFigures
     * @return list<string>
     */
    private function highlights(array $rows, array $leftFigures, array $rightFigures): array
    {
        $highlights = [];
        $issued = collect($rows)->firstWhere('key', 'issued');

        $highlights[] = match (true) {
            $issued['left'] === $issued['right'] => "CARs issued unchanged at {$issued['right']}.",
            $issued['left'] === 0 => "CARs issued went from none to {$issued['right']}.",
            default => 'CARs issued '.($issued['right'] > $issued['left'] ? 'up' : 'down').' '.round(abs($issued['right'] - $issued['left']) / $issued['left'] * 100)."% ({$issued['left']} → {$issued['right']}).",
        };

        $movers = collect($this->breakdown(
            collect($leftFigures['subcategories'])->mapWithKeys(fn (array $row): array => [$row['subcategory'] => $row]),
            collect($rightFigures['subcategories'])->mapWithKeys(fn (array $row): array => [$row['subcategory'] => $row]),
        ))->map(fn (array $row): array => [...$row, 'diff' => $row['right'] - $row['left']]);

        if (($rise = $movers->sortByDesc('diff')->first()) && $rise['diff'] > 0) {
            $highlights[] = "Biggest rise: {$rise['name']} ({$rise['left']} → {$rise['right']}).";
        } elseif (($fall = $movers->sortBy('diff')->first()) && $fall['diff'] < 0) {
            $highlights[] = "Biggest drop: {$fall['name']} ({$fall['left']} → {$fall['right']}).";
        }

        $onTime = collect($rows)->firstWhere('key', 'on_time_response_rate');
        $resolution = collect($rows)->firstWhere('key', 'avg_resolution_days');

        if ($onTime['left'] !== null && $onTime['right'] !== null && $onTime['left'] !== $onTime['right']) {
            $highlights[] = 'On-time response '.($onTime['right'] > $onTime['left'] ? 'improved' : 'dropped')." from {$onTime['left']}% to {$onTime['right']}%.";
        } elseif ($resolution['left'] !== null && $resolution['right'] !== null && $resolution['left'] != $resolution['right']) {
            $highlights[] = 'Average resolution '.($resolution['right'] < $resolution['left'] ? 'faster' : 'slower')." — {$resolution['left']} d → {$resolution['right']} d.";
        }

        return $highlights;
    }
}
