<?php

use App\Livewire\Auth\Login;
use App\Models\AccessLog;
use App\Models\User;
use Livewire\Livewire;

it('records one entry for a successful sign-in', function () {
    $user = User::factory()->create();

    Livewire::test(Login::class)
        ->set('email', $user->email)
        ->set('password', 'password')
        ->call('login');

    expect(AccessLog::sole())
        ->event->toBe('login')
        ->user_id->toBe($user->id)
        ->email->toBe($user->email)
        ->ip_address->not->toBeNull();
});

it('records a failed sign-in with the attempted email', function () {
    $user = User::factory()->create();

    Livewire::test(Login::class)
        ->set('email', $user->email)
        ->set('password', 'wrong-password')
        ->call('login');

    expect(AccessLog::sole())
        ->event->toBe('failed')
        ->email->toBe($user->email);
});

it('records a failed sign-in for an unknown email without a user', function () {
    Livewire::test(Login::class)
        ->set('email', 'nobody@car.test')
        ->set('password', 'password')
        ->call('login');

    expect(AccessLog::sole())
        ->event->toBe('failed')
        ->user_id->toBeNull()
        ->email->toBe('nobody@car.test');
});

it('records a sign-out', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('logout'));

    expect(AccessLog::sole())
        ->event->toBe('logout')
        ->user_id->toBe($user->id);
});
