<?php

use App\Enums\Role;
use App\Livewire\Cars\Show;
use App\Models\User;
use Livewire\Livewire;

it('returns 404 for a reference that does not exist', function () {
    $this->actingAs(User::factory()->role(Role::Monitor)->create())
        ->get(route('cars.show', 'CAR-2026-9999'))
        ->assertNotFound();
});

it('offers the owner the buttons for the current step', function () {
    $approver = User::factory()->role(Role::RequestorApprover)->create();

    Livewire::actingAs($approver)
        ->test(Show::class, ['reference' => 'CAR-2026-0147'])
        ->assertSee('Your action')
        ->assertSee('Approve & release')
        ->assertSee('Reject CAR');
});

it('offers no buttons to a role that does not own the current step', function () {
    $responder = User::factory()->role(Role::Responder)->create();

    Livewire::actingAs($responder)
        ->test(Show::class, ['reference' => 'CAR-2026-0147'])
        ->assertDontSee('Your action')
        ->assertDontSee('Approve & release');
});

it('offers no buttons to a responder from another farm', function () {
    $responder = User::factory()->role(Role::Responder, 'HATCHERY')->create();

    Livewire::actingAs($responder)
        ->test(Show::class, ['reference' => 'CAR-2026-0142'])
        ->assertDontSee('Submit response');
});

it('explains which phase wires up a stub action without changing anything', function () {
    $approver = User::factory()->role(Role::RequestorApprover)->create();

    Livewire::actingAs($approver)
        ->test(Show::class, ['reference' => 'CAR-2026-0147'])
        ->call('runAction', 'Approve & release')
        ->assertSee('wired up in Phase 3')
        ->assertSee('Awaiting Release');
});

it('forbids an action the current user is not offered', function () {
    $responder = User::factory()->role(Role::Responder)->create();

    Livewire::actingAs($responder)
        ->test(Show::class, ['reference' => 'CAR-2026-0147'])
        ->call('runAction', 'Approve & release')
        ->assertForbidden();
});

it('asks for a new end date only at final acceptance', function () {
    $approver = User::factory()->role(Role::RequestorApprover)->create();

    Livewire::actingAs($approver)
        ->test(Show::class, ['reference' => 'CAR-2026-0145'])
        ->assertSee('New end date (only used if not accepted)');

    Livewire::actingAs($approver)
        ->test(Show::class, ['reference' => 'CAR-2026-0147'])
        ->assertDontSee('New end date (only used if not accepted)');
});
