<?php

use App\Enums\CarStatus;
use App\Models\Car;
use App\Models\Farm;
use App\Services\PeriodComparison;
use Carbon\CarbonImmutable;

beforeEach(function () {
    $this->today = CarbonImmutable::parse('2026-10-21');
});

/**
 * @param  array<string, mixed>  $left
 * @param  array<string, mixed>  $right
 * @return array<string, mixed>
 */
function compare(array $left, array $right, bool $sameFilters = true, bool $sameSpan = true): array
{
    return (new PeriodComparison($left, $right, $sameFilters, $sameSpan, test()->today))->build();
}

function row(array $comparison, string $key): array
{
    return collect($comparison['rows'])->firstWhere('key', $key);
}

it('compares two quarters, counting only CARs issued inside each', function () {
    Car::factory()->create(['issued_on' => '2025-02-10']);
    Car::factory()->create(['issued_on' => '2025-05-10']);
    Car::factory()->count(3)->create(['issued_on' => '2026-03-01']);

    $comparison = compare(['year' => 2025, 'period' => 'q1'], ['year' => 2026, 'period' => 'q1']);

    expect(row($comparison, 'issued'))->toMatchArray(['left' => 1, 'right' => 3, 'change' => '▲ 2 (+200%)', 'tone' => 'worse'])
        ->and($comparison['left']['label'])->toBe('Q1 2025')
        ->and($comparison['right']['range'])->toBe('Jan 1, 2026 – Mar 31, 2026')
        ->and($comparison['cut'])->toBeFalse()
        ->and(collect($comparison['months'])->pluck('right.label')->all())->toBe(['Jan 2026', 'Feb 2026', 'Mar 2026']);
});

it('cuts an unfinished year at today and the other year to the same span', function () {
    Car::factory()->create(['issued_on' => '2025-10-01']);
    Car::factory()->create(['issued_on' => '2025-11-15']);
    Car::factory()->create(['issued_on' => '2026-10-20']);

    $cut = compare(['year' => 2025, 'period' => 'year'], ['year' => 2026, 'period' => 'year']);
    $whole = compare(['year' => 2025, 'period' => 'year'], ['year' => 2026, 'period' => 'year'], sameSpan: false);

    expect($cut['cut'])->toBeTrue()
        ->and($cut['left']['range'])->toBe('Jan 1, 2025 – Oct 21, 2025')
        ->and($cut['right']['range'])->toBe('Jan 1, 2026 – Oct 21, 2026')
        ->and(row($cut, 'issued'))->toMatchArray(['left' => 1, 'right' => 1, 'change' => 'no change'])
        ->and(row($whole, 'issued')['left'])->toBe(2);
});

it('marks fewer CARs and faster response as better', function () {
    Car::factory()->count(4)->create(['issued_on' => '2025-02-01']);
    Car::factory()->create(['issued_on' => '2026-02-01']);

    expect(row(compare(['year' => 2025, 'period' => 'q1'], ['year' => 2026, 'period' => 'q1']), 'issued'))
        ->toMatchArray(['change' => '▼ 3 (−75%)', 'tone' => 'better']);
});

it('works out the closure rate and what is still open', function () {
    Car::factory()->status(CarStatus::ClosedAccepted)->count(3)->create(['issued_on' => '2026-01-10']);
    Car::factory()->status(CarStatus::AwaitingResponder)->create(['issued_on' => '2026-01-10']);
    Car::factory()->status(CarStatus::Voided)->create(['issued_on' => '2026-01-10']);

    $comparison = compare(['year' => 2025, 'period' => 'q1'], ['year' => 2026, 'period' => 'q1']);

    expect(row($comparison, 'closure_rate'))->toMatchArray(['right' => 75, 'right_text' => '75%', 'left' => null, 'change' => null])
        ->and(row($comparison, 'still_open')['right'])->toBe(1);
});

it('applies the left filters to both sides, or each side its own', function () {
    $pfc = Farm::firstOrCreate(['name' => 'PFC']);
    $hatchery = Farm::firstOrCreate(['name' => 'HATCHERY']);
    Car::factory()->forFarm('PFC')->create(['issued_on' => '2026-02-01']);
    Car::factory()->forFarm('HATCHERY')->count(2)->create(['issued_on' => '2026-02-01']);

    $same = compare(['year' => 2026, 'period' => 'q1', 'farm' => $pfc->id], ['year' => 2026, 'period' => 'q1', 'farm' => $hatchery->id]);
    $separate = compare(['year' => 2026, 'period' => 'q1', 'farm' => $pfc->id], ['year' => 2026, 'period' => 'q1', 'farm' => $hatchery->id], sameFilters: false);

    expect(row($same, 'issued'))->toMatchArray(['left' => 1, 'right' => 1])
        ->and($same['right']['filters'])->toBe('All business lines · PFC')
        ->and(row($separate, 'issued'))->toMatchArray(['left' => 1, 'right' => 2])
        ->and($separate['right']['filters'])->toBe('All business lines · HATCHERY');
});

it('compares custom dates and single months', function () {
    Car::factory()->create(['issued_on' => '2026-03-15']);
    Car::factory()->create(['issued_on' => '2026-04-15']);

    $comparison = compare(['year' => 2026, 'period' => 'm03'], ['year' => 2026, 'period' => 'custom', 'from' => '2026-03-01', 'to' => '2026-04-30']);

    expect($comparison['left']['label'])->toBe('March 2026')
        ->and($comparison['right']['label'])->toBe('Mar 1, 2026 – Apr 30, 2026')
        ->and(row($comparison, 'issued'))->toMatchArray(['left' => 1, 'right' => 2]);
});

it('lists every unit, farm, category and sub-category seen on either side, and writes highlights', function () {
    Car::factory()->forFarm('PFC')->create(['issued_on' => '2025-02-01']);
    Car::factory()->forFarm('HATCHERY')->count(3)->create(['issued_on' => '2026-02-01']);

    $comparison = compare(['year' => 2025, 'period' => 'q1'], ['year' => 2026, 'period' => 'q1']);

    expect(collect($comparison['breakdowns']['farms']['rows'])->map(fn (array $row): array => [$row['name'], $row['left'], $row['right']])->all())
        ->toBe([['HATCHERY', 0, 3], ['PFC', 1, 0]])
        ->and($comparison['breakdowns']['subcategories']['rows'])->toHaveCount(4)
        ->and($comparison['highlights'][0])->toBe('CARs issued up 200% (1 → 3).');
});
