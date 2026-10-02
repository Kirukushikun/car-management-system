<?php

use App\Enums\Role;
use App\Livewire\Auth\Login;
use App\Models\User;
use Livewire\Livewire;

it('renders the sign-in page for guests', function () {
    $this->get(route('login'))
        ->assertOk()
        ->assertSeeLivewire(Login::class)
        ->assertSee('accounts are not self-registered');
});

it('redirects signed-in users away from the sign-in page to their home page', function () {
    $user = User::factory()->role(Role::Admin)->create();

    $this->actingAs($user)->get(route('login'))->assertRedirect(route('admin.users'));
});

it('signs in with valid credentials and lands on the role home page', function () {
    $user = User::factory()->role(Role::Responder)->create();

    Livewire::test(Login::class)
        ->set('email', $user->email)
        ->set('password', 'password')
        ->call('login')
        ->assertHasNoErrors()
        ->assertRedirect(route('cars.index', ['view' => 'mine']));

    $this->assertAuthenticatedAs($user);
});

it('rejects a wrong password', function () {
    $user = User::factory()->create();

    Livewire::test(Login::class)
        ->set('email', $user->email)
        ->set('password', 'wrong-password')
        ->call('login')
        ->assertHasErrors(['email' => __('auth.failed')]);

    $this->assertGuest();
});

it('rejects a deactivated account even with the right password', function () {
    $user = User::factory()->inactive()->create();

    Livewire::test(Login::class)
        ->set('email', $user->email)
        ->set('password', 'password')
        ->call('login')
        ->assertHasErrors('email');

    $this->assertGuest();
});

it('requires an email and a password', function () {
    Livewire::test(Login::class)
        ->call('login')
        ->assertHasErrors(['email' => 'required', 'password' => 'required']);
});

it('locks the email out after five failed attempts', function () {
    $user = User::factory()->create();
    $component = Livewire::test(Login::class)
        ->set('email', $user->email)
        ->set('password', 'wrong-password');

    foreach (range(1, 5) as $attempt) {
        $component->call('login');
    }

    $component->set('password', 'password')->call('login');

    expect($component->errors()->first('email'))->toStartWith('Too many login attempts.');
    $this->assertGuest();
});

it('lists one account per role and signs in without a password in the local environment', function () {
    app()->detectEnvironment(fn (): string => 'local');
    $monitor = User::factory()->role(Role::Monitor)->create(['name' => 'Quinn Monitor']);
    User::factory()->role(Role::Monitor)->create(['name' => 'Second Monitor']);

    Livewire::test(Login::class)
        ->assertSee('Quinn Monitor')
        ->assertDontSee('Second Monitor')
        ->call('loginAs', $monitor->id)
        ->assertRedirect(route('dashboard'));

    $this->assertAuthenticatedAs($monitor);
});

it('hides the role switcher and refuses it outside the local environment', function () {
    $user = User::factory()->create(['name' => 'Hidden Person']);

    Livewire::test(Login::class)
        ->assertDontSee('Hidden Person')
        ->call('loginAs', $user->id)
        ->assertNotFound();

    $this->assertGuest();
});

it('does not let the role switcher sign in as a deactivated account', function () {
    app()->detectEnvironment(fn (): string => 'local');
    $user = User::factory()->inactive()->create();

    Livewire::test(Login::class)
        ->call('loginAs', $user->id)
        ->assertNotFound();

    $this->assertGuest();
});
