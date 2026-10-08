<?php

use App\Enums\Role;
use App\Models\User;
use Illuminate\Support\Facades\Crypt;

it('signs in a user sent from another system with an encrypted id', function () {
    $user = User::factory()->role(Role::Monitor)->create();

    $this->get(route('app.login', Crypt::encryptString((string) $user->id)))
        ->assertRedirect(route('dashboard'));

    $this->assertAuthenticatedAs($user);
});

it('rejects a tampered id', function () {
    $this->get(route('app.login', 'not-an-encrypted-id'))
        ->assertOk()
        ->assertSee('Login Error [0]. Invalid or tampered ID.');

    $this->assertGuest();
});

it('rejects someone without access, or deactivated', function (bool $exists) {
    $id = $exists ? User::factory()->inactive()->create()->id : 999;

    $this->get(route('app.login', Crypt::encryptString((string) $id)))
        ->assertSee('Login Error [2]. No access to this system.');

    $this->assertGuest();
})->with(['unknown user' => [false], 'deactivated user' => [true]]);

it('sends an already signed-in user to their home page', function () {
    $user = User::factory()->role(Role::Admin)->create();

    $this->actingAs($user)->get(route('app.login', 'anything'))->assertRedirect(route('admin.users'));
});
