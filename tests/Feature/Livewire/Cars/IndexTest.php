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

    $seesButton ? $component->assertSeeHtml('href="'.route('cars.create').'"') : $component->assertDontSeeHtml('href="'.route('cars.create').'"');
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

describe('pagination', function () {
    beforeEach(function () {
        $this->monitor = User::factory()->role(Role::Monitor)->create();
        foreach (range(1, 30) as $day) {
            Car::factory()->create(['issued_on' => now()->subDays($day)->toDateString(), 'reference' => sprintf('CAR-2026-%04d', 100 + $day)]);
        }
    });

    it('shows 25 CARs a page with a footer saying which ones', function () {
        Livewire::actingAs($this->monitor)
            ->test(Index::class)
            ->assertSee(['CAR-2026-0101', 'CAR-2026-0125', 'Showing', '1–25', 'of', '30', 'Rows per page'])
            ->assertDontSee('CAR-2026-0126')
            ->call('nextPage')
            ->assertSee(['CAR-2026-0126', 'CAR-2026-0130', '26–30'])
            ->assertDontSee('CAR-2026-0101');
    });

    it('changes the rows per page and starts again from the first page', function () {
        Livewire::actingAs($this->monitor)
            ->test(Index::class)
            ->call('gotoPage', 2)
            ->set('perPage', 10)
            ->assertSet('paginators.page', 1)
            ->assertSee(['1–10', 'CAR-2026-0110'])
            ->assertDontSee('CAR-2026-0111')
            ->set('perPage', 100)
            ->assertSee(['1–30', 'CAR-2026-0130']);
    });

    it('falls back to 25 rows for a size it does not offer', function () {
        Livewire::withQueryParams(['perPage' => 7])
            ->actingAs($this->monitor)
            ->test(Index::class)
            ->assertSee('1–25');
    });
});
