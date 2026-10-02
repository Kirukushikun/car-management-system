<?php

use App\Enums\Role;
use App\Models\User;

it('signs out a user whose account was deactivated mid-session', function () {
    $user = User::factory()->role(Role::Monitor)->inactive()->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertRedirect(route('login'))
        ->assertSessionHas('status', 'Your account has been deactivated. Contact the IT Admin.');

    $this->assertGuest();
});

it('lets an active user through', function () {
    $user = User::factory()->role(Role::Monitor)->create();

    $this->actingAs($user)->get(route('dashboard'))->assertOk();
});
