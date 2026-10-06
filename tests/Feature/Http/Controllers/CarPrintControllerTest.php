<?php

use App\Enums\Role;
use App\Models\AccessLog;
use App\Models\Car;
use App\Models\User;
use Database\Seeders\ReferenceDataSeeder;
use Database\Seeders\SampleCarSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Support\Facades\Storage;

it('prints a new CAR with Part I and II filled and the rest blank', function () {
    $car = Car::factory()->create(['complainant' => 'Rollie Funa', 'problem_details' => '34 trays returned as spoiled.']);

    $this->actingAs(User::factory()->role(Role::Monitor)->create())
        ->get(route('cars.print', $car))
        ->assertOk()
        ->assertSee(['CORRECTIVE ACTION REPORT FORM', $car->reference, 'ROLLIE FUNA', '34 trays returned as spoiled.', '1st Verification', '2nd Verification']);
});

it('prints a closed CAR through all six parts', function () {
    Storage::fake('local');
    $this->seed([ReferenceDataSeeder::class, UserSeeder::class, SampleCarSeeder::class]);
    $closed = Car::where('reference', 'CAR-2026-0138')->sole();

    $this->actingAs(User::factory()->role(Role::Monitor)->create())
        ->get(route('cars.print', $closed))
        ->assertOk()
        ->assertSeeInOrder([
            'Part I.', 'CAR-2026-0138', 'QA SAMPLING TEAM',
            'Part II.', 'Thin and cracked shells',
            'Part III.', 'Held the morning collection', 'Calcium supplement', 'minimum-stock alert',
            'Part V.', 'Evidence uploaded',
            'Part VI.', 'Verified by: Reneliza M. Yusi', 'Noted by: Stephanie Flores',
        ]);
});

it('sends guests to sign in', function () {
    $this->get(route('cars.print', Car::factory()->create()))->assertRedirect(route('login'));
});

it('returns 404 for an unknown reference', function () {
    $this->actingAs(User::factory()->role(Role::Monitor)->create())
        ->get('/cars/CAR-2026-9999/print')
        ->assertNotFound();
});

it('writes each print to the access log', function () {
    $car = Car::factory()->create();

    $this->actingAs(User::factory()->role(Role::Monitor)->create())->get(route('cars.print', $car))->assertOk();

    expect(AccessLog::where('event', 'print')->sole()->subject)->toBe($car->reference);
});
