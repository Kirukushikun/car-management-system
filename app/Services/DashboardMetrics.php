<?php

namespace App\Services;

use App\Enums\CarAction;
use App\Enums\CarStatus;
use App\Models\Car;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * The figures behind the Monitor dashboard (requirement: repeat offenses by category and
 * sub-category, open/closed status, response time, resolution time, frequency of issuance).
 *
 * Volumes are small (tens of CARs a month), so figures are computed in PHP from one query rather
 * than per-database SQL, which keeps them identical on SQLite and MySQL.
 */
class DashboardMetrics
{
    /**
     * @param  int|null  $days  Look-back period for issued CARs; null means all time.
     */
    public function __construct(
        private ?int $days = 90,
        private ?int $businessLineId = null,
        private ?int $farmId = null,
        private ?CarbonImmutable $today = null,
    ) {
        $this->today ??= CarbonImmutable::today();
    }

    /**
     * @return array{
     *     open: int, overdue: int, phase_one: int, issued: int, closed: int, voided: int,
     *     avg_response_days: ?float, on_time_response_rate: ?int, avg_resolution_days: ?float,
     *     months: array<string, int>,
     *     repeat_offenses: list<array{category: string, subcategory: string, line: string, count: int}>,
     *     by_unit: list<array{unit: string, count: int}>
     * }
     */
    public function summary(): array
    {
        $current = $this->scoped(Car::query())->get(['id', 'status', 'response_due_on', 'implementation_due_on', 'revised_due_on']);
        $issued = $this->issuedInPeriod();

        return [
            'open' => $current->filter(fn (Car $car): bool => $car->status->isOpen())->count(),
            'overdue' => $current->filter(fn (Car $car): bool => $car->isOverdue($this->today))->count(),
            'phase_one' => $current->filter(fn (Car $car): bool => $car->status->phase() === 1)->count(),
            'issued' => $issued->count(),
            'closed' => $issued->where('status', CarStatus::ClosedAccepted)->count(),
            'voided' => $issued->where('status', CarStatus::Voided)->count(),
            ...$this->responseTimes($issued),
            'avg_resolution_days' => $this->averageResolutionDays($issued),
            'months' => $this->monthlyFrequency(),
            'repeat_offenses' => $this->repeatOffenses($issued),
            'by_unit' => $issued->countBy(fn (Car $car): string => $car->issuedToUnit->name)
                ->sortDesc()->take(7)
                ->map(fn (int $count, string $unit): array => ['unit' => $unit, 'count' => $count])
                ->values()->all(),
        ];
    }

    /**
     * Days from issue to the first submitted response, and the share answered within the
     * response deadline.
     *
     * @param  Collection<int, Car>  $cars
     * @return array{avg_response_days: ?float, on_time_response_rate: ?int}
     */
    private function responseTimes(Collection $cars): array
    {
        $responded = $cars
            ->map(fn (Car $car): ?array => ($first = $car->events->firstWhere('action', CarAction::SubmitResponse))
                ? ['days' => $car->issued_on->diffInDays($first->created_at->startOfDay()), 'on_time' => $first->created_at->startOfDay()->lte($car->response_due_on)]
                : null)
            ->filter();

        if ($responded->isEmpty()) {
            return ['avg_response_days' => null, 'on_time_response_rate' => null];
        }

        return [
            'avg_response_days' => round($responded->avg('days'), 1),
            'on_time_response_rate' => (int) round($responded->where('on_time', true)->count() / $responded->count() * 100),
        ];
    }

    /**
     * Days from issue to closure, for CARs issued in the period that are now closed.
     *
     * @param  Collection<int, Car>  $cars
     */
    private function averageResolutionDays(Collection $cars): ?float
    {
        $closed = $cars->filter(fn (Car $car): bool => $car->closed_at !== null);

        return $closed->isEmpty()
            ? null
            : round($closed->avg(fn (Car $car): float => $car->issued_on->diffInDays($car->closed_at->startOfDay())), 1);
    }

    /**
     * CARs issued per month for the last six months, oldest first.
     *
     * @return array<string, int>
     */
    private function monthlyFrequency(): array
    {
        $start = $this->today->startOfMonth()->subMonths(5);
        $counts = $this->scoped(Car::query())
            ->where('issued_on', '>=', $start->toDateString())
            ->get(['issued_on'])
            ->countBy(fn (Car $car): string => $car->issued_on->format('Y-m'));

        return collect(range(0, 5))
            ->mapWithKeys(fn (int $offset): array => [
                $start->addMonths($offset)->format('M') => $counts[$start->addMonths($offset)->format('Y-m')] ?? 0,
            ])
            ->all();
    }

    /**
     * Category / sub-category pairs issued more than once in the period, most frequent first.
     *
     * @param  Collection<int, Car>  $cars
     * @return list<array{category: string, subcategory: string, line: string, count: int}>
     */
    private function repeatOffenses(Collection $cars): array
    {
        return $cars
            ->groupBy('subcategory_id')
            ->filter(fn (Collection $group): bool => $group->count() > 1)
            ->map(fn (Collection $group): array => [
                'category' => $group->first()->category->name,
                'subcategory' => $group->first()->subcategory->name,
                'line' => $group->first()->category->businessLine->name,
                'count' => $group->count(),
            ])
            ->sortByDesc('count')
            ->take(8)
            ->values()
            ->all();
    }

    /**
     * @return Collection<int, Car>
     */
    private function issuedInPeriod(): Collection
    {
        return $this->scoped(Car::query())
            ->when($this->days !== null, fn (Builder $query) => $query->where('issued_on', '>=', $this->today->subDays($this->days)->toDateString()))
            ->with([
                'category.businessLine', 'subcategory', 'issuedToUnit',
                'events' => fn ($query) => $query->where('action', CarAction::SubmitResponse),
            ])
            ->get();
    }

    /**
     * @param  Builder<Car>  $query
     * @return Builder<Car>
     */
    private function scoped(Builder $query): Builder
    {
        return $query
            ->when($this->businessLineId, fn (Builder $query) => $query->whereRelation('category', 'business_line_id', $this->businessLineId))
            ->when($this->farmId, fn (Builder $query) => $query->where('farm_id', $this->farmId));
    }
}
