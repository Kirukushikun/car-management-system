<?php

use App\Models\Car;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
    $this->seed(DatabaseSeeder::class);
});

it('renders every sidebar page for every seeded user', function () {
    foreach (User::all() as $user) {
        foreach ([...$user->role->navigation(), ['route' => 'notifications', 'params' => []]] as $item) {
            $this->actingAs($user)
                ->get(route($item['route'], $item['params']))
                ->assertOk();
        }
    }
});

it('renders the detail and print page of every sample CAR for its current owner', function () {
    foreach (Car::all() as $car) {
        $viewer = User::where('role', $car->status->ownerRole() ?? 'monitor')
            ->when($car->status->ownerRole()?->isFarmScoped(), fn ($query) => $query->where('farm_id', $car->farm_id))
            ->first() ?? User::where('role', 'monitor')->first();

        $this->actingAs($viewer)->get(route('cars.show', $car))->assertOk()->assertSee($car->reference);
        $this->actingAs($viewer)->get(route('cars.print', $car))->assertOk();
    }
});
