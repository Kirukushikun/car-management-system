<?php

use App\Enums\CarStatus;
use App\Enums\Role;
use App\Livewire\Cars\Index;
use App\Models\Car;
use App\Models\User;
use Livewire\Livewire;

it('shows a PFC responder only the CARs waiting on a PFC responder', function () {
    $responder = User::factory()->role(Role::Responder, 'PFC')->create();
    $pfcWaiting = Car::factory()->forFarm('PFC')->status(CarStatus::AwaitingResponder)->create();
    $pfcOpen = Car::factory()->forFarm('PFC')->status(CarStatus::OpenNotAccepted)->create();
    $otherFarm = Car::factory()->forFarm('BROOKDALE')->status(CarStatus::AwaitingResponder)->create();
    $notTheirStep = Car::factory()->forFarm('PFC')->status(CarStatus::AwaitingRelease)->create();

    Livewire::actingAs($responder)
        ->withQueryParams(['view' => 'mine'])
        ->test(Index::class)
        ->assertSee([$pfcWaiting->reference, $pfcOpen->reference])
        ->assertDontSee($otherFarm->reference)
        ->assertDontSee($notTheirStep->reference);
});

it('shows the requestor approver the CARs waiting for release or final acceptance', function () {
    $approver = User::factory()->role(Role::RequestorApprover)->create();
    $toRelease = Car::factory()->status(CarStatus::AwaitingRelease)->create();
    $toAccept = Car::factory()->status(CarStatus::AwaitingRequestorApproval)->create();
    $withResponder = Car::factory()->status(CarStatus::AwaitingResponder)->create();

    Livewire::actingAs($approver)
        ->withQueryParams(['view' => 'mine'])
        ->test(Index::class)
        ->assertSee(['My Approvals', $toRelease->reference, $toAccept->reference])
        ->assertDontSee($withResponder->reference);
});

it('shows a requestor only their own returned CARs', function () {
    $requestor = User::factory()->role(Role::Requestor)->create();
    $mine = Car::factory()->status(CarStatus::ReturnedToRequestor)->create(['requestor_id' => $requestor->id]);
    $someoneElses = Car::factory()->status(CarStatus::ReturnedToRequestor)->create();

    Livewire::actingAs($requestor)
        ->withQueryParams(['view' => 'mine'])
        ->test(Index::class)
        ->assertSee($mine->reference)
        ->assertDontSee($someoneElses->reference);
});

it('lists every CAR when no view is chosen', function () {
    $cars = Car::factory()->count(3)->create();

    Livewire::actingAs(User::factory()->role(Role::Monitor)->create())
        ->test(Index::class)
        ->assertSee('All CARs')
        ->assertSee($cars->pluck('reference')->all());
});

it('lists only overdue CARs in the overdue view', function () {
    $late = Car::factory()->status(CarStatus::AwaitingResponder)->create(['response_due_on' => now()->subDay()]);
    $onTime = Car::factory()->status(CarStatus::AwaitingResponder)->create(['response_due_on' => now()->addDay()]);

    Livewire::actingAs(User::factory()->role(Role::Monitor)->create())
        ->withQueryParams(['view' => 'overdue'])
        ->test(Index::class)
        ->assertSee($late->reference)
        ->assertDontSee($onTime->reference);
});

it('falls back to all CARs for an unknown view', function () {
    $car = Car::factory()->create();

    Livewire::actingAs(User::factory()->role(Role::Monitor)->create())
        ->withQueryParams(['view' => 'everything'])
        ->test(Index::class)
        ->assertSet('view', '')
        ->assertSee($car->reference);
});

it('shows the New CAR button only to requestors', function (Role $role, bool $seesButton) {
    $component = Livewire::actingAs(User::factory()->role($role)->create())->test(Index::class);

    $seesButton ? $component->assertSee('+ New CAR') : $component->assertDontSee('+ New CAR');
})->with([
    'requestor' => [Role::Requestor, true],
    'responder' => [Role::Responder, false],
    'admin' => [Role::Admin, false],
]);

it('counts the queue in the sidebar', function () {
    $approver = User::factory()->role(Role::RequestorApprover)->create();
    Car::factory()->count(2)->status(CarStatus::AwaitingRelease)->create();

    $this->actingAs($approver)
        ->get(route('cars.index', ['view' => 'mine']))
        ->assertSeeInOrder(['My Approvals', '2']);
});

it('leaves a response out of My Approvals for the approver who prepared it', function () {
    $approver = User::factory()->role(Role::ResponderApprover, 'PFC')->create();
    $colleague = User::factory()->role(Role::ResponderApprover, 'PFC')->create();
    $own = Car::factory()->forFarm('PFC')->status(CarStatus::AwaitingResponderApproval)->withResponse($approver, submitted: true)->create();

    expect(Car::waitingOn($approver)->pluck('id')->all())->not->toContain($own->id)
        ->and(Car::waitingOn($colleague)->pluck('id')->all())->toContain($own->id);
});

it('routes an approver-prepared response only to that person\'s approver', function () {
    $senior = User::factory()->role(Role::ResponderApprover, 'PFC')->create();
    $other = User::factory()->role(Role::ResponderApprover, 'PFC')->create();
    $preparer = User::factory()->role(Role::ResponderApprover, 'PFC')->create(['approver_id' => $senior->id]);
    $car = Car::factory()->forFarm('PFC')->status(CarStatus::AwaitingResponderApproval)->withResponse($preparer, submitted: true)->create();

    expect(Car::waitingOn($senior)->pluck('id')->all())->toContain($car->id)
        ->and(Car::waitingOn($other)->pluck('id')->all())->not->toContain($car->id);
});
