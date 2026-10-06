<?php

use App\Enums\CarAction;
use App\Enums\Role;
use App\Livewire\Notifications\Index;
use App\Models\Car;
use App\Models\User;
use App\Notifications\CarNeedsAction;
use Livewire\Livewire;

beforeEach(function () {
    $this->user = User::factory()->role(Role::RequestorApprover)->create();
    $this->car = Car::factory()->create();
    $this->user->notifyNow(new CarNeedsAction($this->car, CarAction::Submit, 'Gab Maglalang'), ['database']);
});

it('lists the user\'s flags with the unread count in the sidebar', function () {
    $this->actingAs($this->user)
        ->get(route('notifications'))
        ->assertOk()
        ->assertSee([$this->car->reference, 'A new CAR is waiting for your release', '1 unread'])
        ->assertSeeInOrder(['Notifications', '1']);
});

it('opens the CAR and marks the flag read', function () {
    $notification = $this->user->notifications()->sole();

    Livewire::actingAs($this->user)
        ->test(Index::class)
        ->call('open', $notification->id)
        ->assertRedirect(route('cars.show', $this->car));

    expect($notification->fresh()->read_at)->not->toBeNull();
});

it('marks everything as read', function () {
    Livewire::actingAs($this->user)
        ->test(Index::class)
        ->call('markAllRead')
        ->assertSee('You are all caught up');

    expect($this->user->unreadNotifications()->count())->toBe(0);
});

it('cannot open someone else\'s flag', function () {
    $notification = $this->user->notifications()->sole();

    Livewire::actingAs(User::factory()->create())
        ->test(Index::class)
        ->call('open', $notification->id)
        ->assertNotFound();
});
