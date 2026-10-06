<?php

use App\Enums\CarAction;
use App\Enums\CarStatus;
use App\Models\Car;
use App\Models\CarEvent;
use App\Models\Category;
use App\Models\Farm;
use App\Models\Subcategory;
use App\Services\DashboardMetrics;
use Carbon\CarbonImmutable;

beforeEach(function () {
    $this->today = CarbonImmutable::parse('2026-10-06');
});

function metrics(array $overrides = []): array
{
    return (new DashboardMetrics(...[...['days' => 90, 'today' => test()->today], ...$overrides]))->summary();
}

function respondedOn(Car $car, string $date): void
{
    CarEvent::create(['car_id' => $car->id, 'round' => 1, 'action' => CarAction::SubmitResponse, 'from_status' => CarStatus::AwaitingResponder, 'to_status' => CarStatus::AwaitingResponderApproval, 'actor_id' => $car->requestor_id, 'created_at' => "{$date} 10:00:00"]);
}

it('counts open, overdue and Phase I CARs as they stand today', function () {
    Car::factory()->status(CarStatus::AwaitingResponder)->create(['response_due_on' => '2026-10-01']);
    Car::factory()->status(CarStatus::AwaitingImplementation)->create(['implementation_due_on' => '2026-10-20']);
    Car::factory()->status(CarStatus::ClosedAccepted)->create();

    expect(metrics())
        ->open->toBe(2)
        ->overdue->toBe(1)
        ->phase_one->toBe(1);
});

it('averages the days from issue to the first submitted response and reports how many were on time', function () {
    $onTime = Car::factory()->create(['issued_on' => '2026-09-01', 'response_due_on' => '2026-09-04']);
    $late = Car::factory()->create(['issued_on' => '2026-09-10', 'response_due_on' => '2026-09-11']);
    Car::factory()->create(['issued_on' => '2026-09-20']);
    respondedOn($onTime, '2026-09-03');
    respondedOn($late, '2026-09-14');

    expect(metrics())
        ->avg_response_days->toBe(3.0)
        ->on_time_response_rate->toBe(50);
});

it('averages the days from issue to closure for closed CARs', function () {
    Car::factory()->status(CarStatus::ClosedAccepted)->create(['issued_on' => '2026-09-01', 'closed_at' => '2026-09-08 15:00:00']);
    Car::factory()->status(CarStatus::ClosedAccepted)->create(['issued_on' => '2026-09-10', 'closed_at' => '2026-09-21 09:00:00']);
    Car::factory()->status(CarStatus::AwaitingResponder)->create(['issued_on' => '2026-09-15']);

    expect(metrics())
        ->avg_resolution_days->toBe(9.0)
        ->issued->toBe(3)
        ->closed->toBe(2);
});

it('reports no averages when nothing has been answered or closed', function () {
    Car::factory()->create(['issued_on' => '2026-10-01']);

    expect(metrics())
        ->avg_response_days->toBeNull()
        ->on_time_response_rate->toBeNull()
        ->avg_resolution_days->toBeNull();
});

it('counts CARs issued per month for the last six months', function () {
    Car::factory()->create(['issued_on' => '2026-10-02']);
    Car::factory()->count(2)->create(['issued_on' => '2026-08-15']);
    Car::factory()->create(['issued_on' => '2026-03-31']);

    expect(metrics()['months'])->toBe(['May' => 0, 'Jun' => 0, 'Jul' => 0, 'Aug' => 2, 'Sep' => 0, 'Oct' => 1]);
});

it('lists sub-categories issued more than once in the period as repeat offenses', function () {
    $repeat = Subcategory::factory()->create(['name' => 'Internal Quality Defects']);
    $once = Subcategory::factory()->create(['name' => 'Packaging & Labeling']);
    Car::factory()->count(3)->create(['issued_on' => '2026-09-20', 'category_id' => $repeat->category_id, 'subcategory_id' => $repeat->id]);
    Car::factory()->create(['issued_on' => '2026-09-20', 'category_id' => $once->category_id, 'subcategory_id' => $once->id]);
    Car::factory()->create(['issued_on' => '2026-01-05', 'category_id' => $once->category_id, 'subcategory_id' => $once->id]);

    expect(metrics()['repeat_offenses'])->toHaveCount(1)
        ->and(metrics()['repeat_offenses'][0])->toMatchArray(['subcategory' => 'Internal Quality Defects', 'count' => 3])
        ->and(metrics(['days' => null])['repeat_offenses'])->toHaveCount(2);
});

it('filters by business line and farm', function () {
    $egg = Category::factory()->create();
    Car::factory()->forFarm('PFC')->create(['issued_on' => '2026-09-20']);
    Car::factory()->forFarm('HATCHERY')->create(['issued_on' => '2026-09-20']);
    $pfcFarmId = Farm::where('name', 'PFC')->value('id');

    expect(metrics(['farmId' => $pfcFarmId])['issued'])->toBe(1)
        ->and(metrics(['businessLineId' => $egg->business_line_id])['issued'])->toBe(0);
});
