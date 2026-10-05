<?php

use App\Enums\CarAction;
use App\Enums\CarStatus;
use App\Enums\Role;
use App\Livewire\Cars\Show;
use App\Models\Attachment;
use App\Models\Car;
use App\Models\User;
use Livewire\Livewire;

it('returns 404 for a reference that does not exist', function () {
    $this->actingAs(User::factory()->role(Role::Monitor)->create())
        ->get('/cars/CAR-2026-9999')
        ->assertNotFound();
});

it('opens the detail page by reference inside the app layout', function () {
    $car = Car::factory()->create();

    $this->actingAs(User::factory()->role(Role::Monitor)->create())
        ->get("/cars/{$car->reference}")
        ->assertOk()
        ->assertSeeLivewire(Show::class)
        ->assertSee(['CAR Management System', $car->reference]);
});

it('shows the Phase I details, attachments and history', function () {
    $car = Car::factory()->create(['complainant' => 'Rollie Funa', 'problem_details' => '34 trays returned as spoiled.']);
    Attachment::factory()->create(['attachable_id' => $car->id, 'original_name' => 'spoiled-eggs.mp4', 'mime_type' => 'video/mp4']);
    $car->events()->create(['round' => 1, 'action' => CarAction::Submit, 'to_status' => CarStatus::AwaitingRelease, 'actor_id' => $car->requestor_id, 'created_at' => now()]);

    Livewire::actingAs(User::factory()->role(Role::Monitor)->create())
        ->test(Show::class, ['car' => $car])
        ->assertSee([$car->reference, 'Rollie Funa', '34 trays returned as spoiled.', 'spoiled-eggs.mp4', 'Product Nonconformity'])
        ->assertSee('CAR issued and submitted for release.')
        ->assertSee($car->requestor->name);
});

it('offers the requestor approver release and reject on a CAR awaiting release', function () {
    $car = Car::factory()->status(CarStatus::AwaitingRelease)->create();

    Livewire::actingAs(User::factory()->role(Role::RequestorApprover)->create())
        ->test(Show::class, ['car' => $car])
        ->assertSee(['Your action', 'Approve &amp; release', 'Reject CAR'], false);
});

it('offers no buttons to a role that does not own the current step', function () {
    $car = Car::factory()->status(CarStatus::AwaitingRelease)->create();

    Livewire::actingAs(User::factory()->role(Role::Responder)->create(['farm_id' => $car->farm_id]))
        ->test(Show::class, ['car' => $car])
        ->assertDontSee('Your action');
});

it('releases a CAR to the responder', function () {
    $car = Car::factory()->status(CarStatus::AwaitingRelease)->create();
    $approver = User::factory()->role(Role::RequestorApprover)->create();

    Livewire::actingAs($approver)
        ->test(Show::class, ['car' => $car])
        ->call('act', CarAction::Release->value)
        ->assertSee('CAR approved and released to the Responder.')
        ->assertDontSee('Your action');

    expect($car->fresh())
        ->status->toBe(CarStatus::AwaitingResponder)
        ->released_at->not->toBeNull()
        ->and($car->events()->sole()->actor_id)->toBe($approver->id);
});

it('requires a reason to reject a CAR', function () {
    $car = Car::factory()->status(CarStatus::AwaitingRelease)->create();

    Livewire::actingAs(User::factory()->role(Role::RequestorApprover)->create())
        ->test(Show::class, ['car' => $car])
        ->call('act', CarAction::Reject->value)
        ->assertHasErrors('note')
        ->assertSee('Give a reason for');

    expect($car->fresh()->status)->toBe(CarStatus::AwaitingRelease);
});

it('rejects a CAR back to the requestor with the reason', function () {
    $car = Car::factory()->status(CarStatus::AwaitingRelease)->create();

    Livewire::actingAs(User::factory()->role(Role::RequestorApprover)->create())
        ->test(Show::class, ['car' => $car])
        ->set('note', 'Please attach the dispatch form.')
        ->call('act', CarAction::Reject->value)
        ->assertHasNoErrors()
        ->assertSee('Please attach the dispatch form.');

    expect($car->fresh()->status)->toBe(CarStatus::ReturnedToRequestor)
        ->and($car->events()->sole()->note)->toBe('Please attach the dispatch form.');
});

it('sends the filing requestor to the correction form on a returned CAR', function () {
    $car = Car::factory()->status(CarStatus::ReturnedToRequestor)->create();

    Livewire::actingAs($car->requestor)
        ->test(Show::class, ['car' => $car])
        ->assertSee('Correct &amp; resubmit', false)
        ->assertSee(route('cars.edit', $car));
});

it('lets the IT Admin void an open CAR with a reason', function () {
    $car = Car::factory()->status(CarStatus::AwaitingResponder)->create();

    Livewire::actingAs(User::factory()->role(Role::Admin)->create())
        ->test(Show::class, ['car' => $car])
        ->assertSee('As IT Admin you can void this CAR')
        ->set('note', 'Duplicate entry.')
        ->call('act', CarAction::Void->value)
        ->assertHasNoErrors();

    expect($car->fresh()->status)->toBe(CarStatus::Voided);
});

it('explains which phase wires up a Phase II or III action without changing anything', function () {
    $car = Car::factory()->status(CarStatus::AwaitingResponder)->create();

    Livewire::actingAs(User::factory()->role(Role::Responder)->create(['farm_id' => $car->farm_id]))
        ->test(Show::class, ['car' => $car])
        ->call('act', CarAction::SubmitResponse->value)
        ->assertSee('wired up in Phase 4');

    expect($car->fresh()->status)->toBe(CarStatus::AwaitingResponder)
        ->and($car->events()->count())->toBe(0);
});

it('forbids an action the current user is not offered', function () {
    $car = Car::factory()->status(CarStatus::AwaitingRelease)->create();

    Livewire::actingAs(User::factory()->role(Role::Responder)->create(['farm_id' => $car->farm_id]))
        ->test(Show::class, ['car' => $car])
        ->call('act', CarAction::Release->value)
        ->assertForbidden();

    expect($car->fresh()->status)->toBe(CarStatus::AwaitingRelease);
});
