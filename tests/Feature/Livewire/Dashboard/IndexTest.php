<?php

use App\Enums\CarStatus;
use App\Enums\Role;
use App\Livewire\Dashboard\Index;
use App\Models\Car;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->monitor = User::factory()->role(Role::Monitor)->create();
});

it('shows live figures and the overdue list', function () {
    $late = Car::factory()->status(CarStatus::AwaitingResponder)->create(['issued_on' => now()->subDays(5), 'response_due_on' => now()->subDay()]);

    Livewire::actingAs($this->monitor)
        ->test(Index::class)
        ->assertSee(['Open CARs', 'needs attention', 'Last 90 days', $late->reference]);
});

it('refilters when the period changes', function () {
    Car::factory()->create(['issued_on' => now()->subDays(200)]);

    Livewire::actingAs($this->monitor)
        ->test(Index::class)
        ->assertSee('0 closed of 0 issued')
        ->set('period', 'all')
        ->assertSee('0 closed of 1 issued');
});

it('falls back to 90 days for an unknown period', function () {
    Livewire::actingAs($this->monitor)
        ->withQueryParams(['period' => '9999'])
        ->test(Index::class)
        ->assertSet('period', '90');
});

it('is only for the Monitor', function () {
    $this->actingAs(User::factory()->role(Role::Requestor)->create())->get(route('dashboard'))->assertForbidden();
});

it('has a Dashboard guide explaining the filters and each figure', function () {
    Livewire::actingAs($this->monitor)
        ->test(Index::class)
        ->assertSeeHtml('open-guide')
        ->assertSeeInOrder([
            'Guide', 'Dashboard guide',
            'Overview', 'Reading the figures',
            'Pick a period', 'Narrow it down', 'Act on what needs attention',
            'Open CARs', 'Overdue', 'Avg. response time', 'Avg. resolution time',
            'Repeat offenses', 'A CAR with several sub-categories counts under each.',
            'Issued by unit',
        ]);
});

it('pages the overdue table, 10 at a time, and starts again when a filter changes', function () {
    foreach (range(1, 12) as $i) {
        Car::factory()->status(CarStatus::AwaitingResponder)->create(['response_due_on' => now()->subDays($i)->toDateString()]);
    }

    Livewire::actingAs($this->monitor)
        ->test(Index::class)
        ->assertSee(['1–10', 'of', '12', 'overdue CARs'])
        ->call('nextPage', 'overduePage')
        ->assertSee('11–12')
        ->set('period', '30')
        ->assertSee('1–10')
        ->call('nextPage', 'overduePage')
        ->set('perPage', 25)
        ->assertSet('paginators.overduePage', 1)
        ->assertSee('1–12');
});
