<?php

use App\Livewire\Auth\Login;
use App\Models\AccessLog;
use App\Models\User;
use Livewire\Livewire;

it('records one successful entry for a sign-in', function () {
    $user = User::factory()->sample()->create();

    Livewire::test(Login::class)
        ->set('email', $user->email)
        ->set('password', 'password')
        ->call('login');

    expect(AccessLog::sole())
        ->event->toBe('login')
        ->success->toBeTrue()
        ->user_id->toBe($user->id)
        ->email->toBe($user->email)
        ->ip_address->not->toBeNull();
});

it('records a sign-out', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('logout'));

    expect(AccessLog::sole())
        ->event->toBe('logout')
        ->success->toBeNull()
        ->user_id->toBe($user->id);
});
