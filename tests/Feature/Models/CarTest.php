<?php

use App\Enums\CarStatus;
use App\Models\Car;
use App\Models\CarEvent;
use Carbon\CarbonImmutable;

it('locks Phase I details once the CAR has been released', function () {
    $car = Car::factory()->status(CarStatus::AwaitingResponder)->create();

    expect(fn () => $car->update(['complainant' => 'Someone else']))
        ->toThrow(LogicException::class, "Phase I details of {$car->reference} are locked after release.");
    expect($car->fresh()->complainant)->not->toBe('Someone else');
});

it('lets Phase I details change before release', function () {
    $car = Car::factory()->status(CarStatus::ReturnedToRequestor)->create();

    $car->update(['complainant' => 'Corrected name']);

    expect($car->fresh()->complainant)->toBe('Corrected name');
});

it('lets workflow fields change after release', function () {
    $car = Car::factory()->status(CarStatus::AwaitingResponder)->create();

    $car->update(['status' => CarStatus::AwaitingResponderApproval]);

    expect($car->fresh()->status)->toBe(CarStatus::AwaitingResponderApproval);
});

it('uses the deadline that matches the current phase', function (CarStatus $status, ?string $revised, ?string $expected) {
    $car = Car::factory()->status($status)->make([
        'response_due_on' => '2026-10-08',
        'implementation_due_on' => '2026-10-15',
        'revised_due_on' => $revised,
    ]);

    expect($car->activeDueOn()?->toDateString())->toBe($expected);
})->with([
    'phase I uses the response deadline' => [CarStatus::AwaitingResponder, null, '2026-10-08'],
    'phase II uses the implementation deadline' => [CarStatus::AwaitingResponderApproval, null, '2026-10-15'],
    'a revised date takes over' => [CarStatus::OpenNotAccepted, '2026-10-30', '2026-10-30'],
    'closed CARs have none' => [CarStatus::ClosedAccepted, null, null],
]);

it('is overdue only after its active deadline has passed', function (string $today, bool $overdue) {
    $car = Car::factory()->status(CarStatus::AwaitingResponder)->make(['response_due_on' => '2026-10-08']);

    expect($car->isOverdue(CarbonImmutable::parse($today)))->toBe($overdue);
})->with([
    'before' => ['2026-10-07', false],
    'on the day' => ['2026-10-08 17:00', false],
    'after' => ['2026-10-09', true],
]);

it('is never overdue once closed or voided', function (CarStatus $status) {
    $car = Car::factory()->status($status)->make(['implementation_due_on' => '2020-01-01']);

    expect($car->isOverdue())->toBeFalse();
})->with([CarStatus::ClosedAccepted, CarStatus::Voided]);

it('names who must act next', function (CarStatus $status, ?string $label) {
    $car = Car::factory()->forFarm('HATCHERY')->status($status)->create();

    expect($car->ownerLabel())->toBe($label);
})->with([
    [CarStatus::AwaitingRelease, 'Requestor Approver'],
    [CarStatus::ReturnedToRequestor, 'Requestor'],
    [CarStatus::AwaitingResponder, 'HATCHERY Responder'],
    [CarStatus::AwaitingEffectivenessCheck, 'HATCHERY Responder Approver'],
    [CarStatus::ClosedAccepted, null],
]);

it('keeps history append-only', function () {
    $event = CarEvent::factory()->create(['created_at' => now()]);

    expect(fn () => $event->update(['note' => 'rewritten']))->toThrow(LogicException::class, 'CAR history is append-only.')
        ->and(fn () => $event->delete())->toThrow(LogicException::class, 'CAR history is append-only.');
});
