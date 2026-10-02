<?php

use App\Enums\Role;
use App\Models\User;

test('the home page sends guests to sign in', function () {
    $this->get('/')->assertRedirect(route('login'));
});

test('the home page sends signed-in users to their role home page', function () {
    $user = User::factory()->role(Role::Monitor)->create();

    $this->actingAs($user)->get('/')->assertRedirect(route('dashboard'));
});
