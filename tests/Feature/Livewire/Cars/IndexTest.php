<?php

use App\Enums\Role;
use App\Livewire\Cars\Index;
use App\Models\User;
use Livewire\Livewire;

it('shows a PFC responder only the CARs waiting on a PFC responder', function () {
    $responder = User::factory()->role(Role::Responder, 'PFC')->create();

    Livewire::actingAs($responder)
        ->withQueryParams(['view' => 'mine'])
        ->test(Index::class)
        ->assertSee('CAR-2026-0142')
        ->assertSee('CAR-2026-0139')
        ->assertDontSee('CAR-2026-0146')
        ->assertDontSee('CAR-2026-0147');
});

it('shows the requestor approver the CARs waiting for release or final acceptance', function () {
    $approver = User::factory()->role(Role::RequestorApprover)->create();

    Livewire::actingAs($approver)
        ->withQueryParams(['view' => 'mine'])
        ->test(Index::class)
        ->assertSee('My Approvals')
        ->assertSee('CAR-2026-0147')
        ->assertSee('CAR-2026-0145')
        ->assertDontSee('CAR-2026-0142');
});

it('lists every CAR when no view is chosen', function () {
    $monitor = User::factory()->role(Role::Monitor)->create();

    Livewire::actingAs($monitor)
        ->test(Index::class)
        ->assertSee('All CARs')
        ->assertSee('CAR-2026-0138')
        ->assertSee('CAR-2026-0147');
});

it('falls back to all CARs for an unknown view', function () {
    $monitor = User::factory()->role(Role::Monitor)->create();

    Livewire::actingAs($monitor)
        ->withQueryParams(['view' => 'everything'])
        ->test(Index::class)
        ->assertSet('view', '')
        ->assertSee('CAR-2026-0138');
});

it('shows the New CAR button only to requestors', function (Role $role, bool $seesButton) {
    $component = Livewire::actingAs(User::factory()->role($role)->create())->test(Index::class);

    $seesButton ? $component->assertSee('+ New CAR') : $component->assertDontSee('+ New CAR');
})->with([
    'requestor' => [Role::Requestor, true],
    'responder' => [Role::Responder, false],
    'admin' => [Role::Admin, false],
]);
