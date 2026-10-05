<?php

use App\Enums\CarStatus;
use App\Models\Car;
use Database\Seeders\ReferenceDataSeeder;
use Database\Seeders\SampleCarSeeder;
use Database\Seeders\UserSeeder;

beforeEach(function () {
    $this->seed([ReferenceDataSeeder::class, UserSeeder::class, SampleCarSeeder::class]);
});

it('replays the mockup sample CARs into the same statuses', function () {
    expect(Car::orderBy('reference')->get()->mapWithKeys(fn (Car $car): array => [$car->reference => $car->status])->all())
        ->toBe([
            'CAR-2026-0138' => CarStatus::ClosedAccepted,
            'CAR-2026-0139' => CarStatus::OpenNotAccepted,
            'CAR-2026-0140' => CarStatus::AwaitingResponderApproval,
            'CAR-2026-0141' => CarStatus::AwaitingImplementation,
            'CAR-2026-0142' => CarStatus::AwaitingResponder,
            'CAR-2026-0143' => CarStatus::AwaitingEffectivenessCheck,
            'CAR-2026-0144' => CarStatus::AwaitingResponder,
            'CAR-2026-0145' => CarStatus::AwaitingRequestorApproval,
            'CAR-2026-0146' => CarStatus::AwaitingResponder,
            'CAR-2026-0147' => CarStatus::AwaitingRelease,
        ]);
});

it('dates the history and deadlines on the original sample dates', function () {
    $closed = Car::where('reference', 'CAR-2026-0138')->sole();

    expect($closed->issued_on->toDateString())->toBe('2026-08-28')
        ->and($closed->implementation_due_on->toDateString())->toBe('2026-09-07')
        ->and($closed->closed_at->toDateString())->toBe('2026-09-05')
        ->and($closed->events()->count())->toBe(7);
});

it('gives the not-accepted sample a second round with its revised end date', function () {
    $car = Car::where('reference', 'CAR-2026-0139')->sole();

    expect($car->current_round)->toBe(2)
        ->and($car->activeDueOn()->toDateString())->toBe('2026-09-20');
});

it('continues numbering after the samples', function () {
    expect(DB::table('car_sequences')->where('year', 2026)->value('last_number'))->toBe(147);
});
