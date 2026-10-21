<?php

namespace App\Livewire\Dashboard;

use App\Models\BusinessLine;
use App\Models\Farm;
use App\Services\PeriodComparison;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Period comparison for the QA Monitor: two periods side by side (a year, half, quarter, month or
 * custom dates), each with its own filters, the change between them and a printable layout. Every
 * choice lives in the URL, so a copied link reopens the same comparison.
 */
class Compare extends Component
{
    #[Url]
    public int $leftYear = 0;

    #[Url]
    public string $leftPeriod = 'year';

    #[Url]
    public ?string $leftFrom = null;

    #[Url]
    public ?string $leftTo = null;

    #[Url]
    public ?int $leftLine = null;

    #[Url]
    public ?int $leftFarm = null;

    #[Url]
    public int $rightYear = 0;

    #[Url]
    public string $rightPeriod = 'year';

    #[Url]
    public ?string $rightFrom = null;

    #[Url]
    public ?string $rightTo = null;

    #[Url]
    public ?int $rightLine = null;

    #[Url]
    public ?int $rightFarm = null;

    /**
     * The right side follows the left side's business line and farm.
     */
    #[Url]
    public bool $sameFilters = true;

    /**
     * An unfinished period is cut at today and the other side to the same span.
     */
    #[Url]
    public bool $sameSpan = true;

    /**
     * Consecutive years: the left year is always the year before the right one. Picking either
     * year moves the other with it (left 2025 → right 2026; right 2024 → left 2023).
     */
    #[Url]
    public bool $linkYears = true;

    public function mount(): void
    {
        $this->authorize('view-dashboard');

        if (! $this->leftYear || ! $this->rightYear) {
            $this->useLastAndThisYear();
        } elseif ($this->linkYears) {
            $this->leftYear = $this->rightYear - 1;
        }

        foreach (['leftPeriod', 'rightPeriod'] as $property) {
            if (! array_key_exists($this->{$property}, PeriodComparison::PERIODS)) {
                $this->{$property} = 'year';
            }
        }

        $this->syncFilters();
    }

    /**
     * With "Same filters on both sides", the right side mirrors the left side's line and farm.
     * With consecutive years, picking one year moves the other with it.
     */
    public function updated(string $property): void
    {
        if ($this->linkYears) {
            match ($property) {
                'leftYear' => $this->rightYear = $this->leftYear + 1,
                'rightYear', 'linkYears' => $this->leftYear = $this->rightYear - 1,
                default => null,
            };
        }

        $this->syncFilters();
    }

    /**
     * Back to the starting comparison: last year against this year, full years, all business lines
     * and farms, same filters and same span.
     */
    public function clear(): void
    {
        $this->reset(['leftPeriod', 'leftFrom', 'leftTo', 'leftLine', 'leftFarm', 'rightPeriod', 'rightFrom', 'rightTo', 'rightLine', 'rightFarm', 'sameFilters', 'sameSpan', 'linkYears']);
        $this->useLastAndThisYear();
    }

    private function useLastAndThisYear(): void
    {
        $this->rightYear = CarbonImmutable::today()->year;
        $this->leftYear = $this->rightYear - 1;
    }

    private function syncFilters(): void
    {
        if ($this->sameFilters) {
            $this->rightLine = $this->leftLine;
            $this->rightFarm = $this->leftFarm;
        }
    }

    /**
     * The current choices, as query parameters for the CSV export.
     *
     * @return array<string, mixed>
     */
    public function exportQuery(): array
    {
        return array_filter([
            'leftYear' => $this->leftYear, 'leftPeriod' => $this->leftPeriod, 'leftFrom' => $this->leftFrom, 'leftTo' => $this->leftTo,
            'leftLine' => $this->leftLine, 'leftFarm' => $this->leftFarm,
            'rightYear' => $this->rightYear, 'rightPeriod' => $this->rightPeriod, 'rightFrom' => $this->rightFrom, 'rightTo' => $this->rightTo,
            'rightLine' => $this->rightLine, 'rightFarm' => $this->rightFarm,
            'sameFilters' => $this->sameFilters ? 1 : 0, 'sameSpan' => $this->sameSpan ? 1 : 0, 'linkYears' => $this->linkYears ? 1 : 0,
        ], fn (mixed $value): bool => $value !== null && $value !== '');
    }

    public function render(): View
    {
        $comparison = (new PeriodComparison(
            left: ['year' => $this->leftYear, 'period' => $this->leftPeriod, 'from' => $this->leftFrom, 'to' => $this->leftTo, 'line' => $this->leftLine, 'farm' => $this->leftFarm],
            right: ['year' => $this->rightYear, 'period' => $this->rightPeriod, 'from' => $this->rightFrom, 'to' => $this->rightTo, 'line' => $this->rightLine, 'farm' => $this->rightFarm],
            sameFilters: $this->sameFilters,
            sameSpan: $this->sameSpan,
        ))->build();

        return view('livewire.dashboard.compare', [
            'comparison' => $comparison,
            'years' => collect([...PeriodComparison::years(), $this->leftYear, $this->rightYear])->unique()->sortDesc()->values()->all(),
            'periods' => PeriodComparison::PERIODS,
            'lines' => BusinessLine::orderBy('id')->get(),
            'farms' => Farm::orderBy('id')->get(),
            'maxMonth' => max([1, ...array_map(fn (array $pair): int => max($pair['left']['count'] ?? 0, $pair['right']['count'] ?? 0), $comparison['months'])]),
            'exportUrl' => route('dashboard.compare.export', $this->exportQuery()),
        ])->title('Period comparison');
    }
}
