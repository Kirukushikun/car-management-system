<?php

use App\Enums\CarStatus;
use App\Models\Car;
use App\Models\CarEvent;
use Database\Seeders\DemoDataSeeder;
use Database\Seeders\ReferenceDataSeeder;
use Database\Seeders\TestSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
    Carbon::setTestNow('2026-10-21 10:00:00');
});

it('files about two years of CARs through the workflow, in every state', function () {
    $this->seed([ReferenceDataSeeder::class, TestSeeder::class, DemoDataSeeder::class]);

    $cars = Car::all();

    expect($cars->count())->toBeGreaterThan(120)
        ->and($cars->min('issued_on')->toDateString())->toStartWith('2025-01')
        ->and($cars->max('issued_on')->lte(now()))->toBeTrue()
        ->and($cars->where('status', CarStatus::ClosedAccepted)->count())->toBeGreaterThan(60)
        ->and($cars->filter(fn (Car $car): bool => $car->status->isOpen())->count())->toBeGreaterThan(5)
        ->and(Car::query()->overdue()->count())->toBeGreaterThan(0)
        ->and(CarEvent::where('created_at', '>', now())->count())->toBe(0)
        ->and($cars->pluck('reference')->first())->toBe('CAR-2025-0001');
});

it('refuses to run in production', function () {
    $this->seed([ReferenceDataSeeder::class, TestSeeder::class]);
    app()->detectEnvironment(fn (): string => 'production');

    expect(fn () => app(DemoDataSeeder::class)->setContainer(app())->__invoke())->toThrow(RuntimeException::class, 'must never run in production');
    expect(Car::count())->toBe(0);
});
