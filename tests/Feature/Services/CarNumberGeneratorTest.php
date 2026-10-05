<?php

use App\Services\CarNumberGenerator;
use Carbon\CarbonImmutable;

it('starts each year at 0001 and counts up', function () {
    $generator = app(CarNumberGenerator::class);
    $day = CarbonImmutable::parse('2026-03-01');

    expect([$generator->next($day), $generator->next($day), $generator->next($day)])
        ->toBe(['CAR-2026-0001', 'CAR-2026-0002', 'CAR-2026-0003']);
});

it('keeps a separate sequence per year', function () {
    $generator = app(CarNumberGenerator::class);

    $generator->next(CarbonImmutable::parse('2026-12-31'));

    expect($generator->next(CarbonImmutable::parse('2027-01-01')))->toBe('CAR-2027-0001')
        ->and($generator->next(CarbonImmutable::parse('2026-12-31')))->toBe('CAR-2026-0002');
});

it('continues from the last number already used', function () {
    DB::table('car_sequences')->insert(['year' => 2026, 'last_number' => 147]);

    expect(app(CarNumberGenerator::class)->next(CarbonImmutable::parse('2026-10-05')))->toBe('CAR-2026-0148');
});
