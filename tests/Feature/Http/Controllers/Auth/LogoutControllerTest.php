<?php

use App\Models\User;

it('signs the user out and returns to the sign-in page', function () {
    $this->actingAs(User::factory()->create())
        ->post(route('logout'))
        ->assertRedirect(route('login'));

    $this->assertGuest();
});

it('redirects guests to sign in instead of signing out', function () {
    $this->post(route('logout'))->assertRedirect(route('login'));
});
