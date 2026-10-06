<?php

use App\Enums\Role;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

it('creates an active IT Admin with the typed password', function () {
    $this->artisan('app:create-admin', ['email' => 'it@bfcgroup.test', 'name' => 'IT Department'])
        ->expectsQuestion('Password (at least 8 characters)', 'secure-pass')
        ->assertSuccessful();

    $admin = User::where('email', 'it@bfcgroup.test')->sole();

    expect($admin)
        ->role->toBe(Role::Admin)
        ->is_active->toBeTrue()
        ->and(Hash::check('secure-pass', $admin->password))->toBeTrue();
});

it('refuses a short password or an email already in use', function () {
    User::factory()->create(['email' => 'taken@bfcgroup.test']);

    $this->artisan('app:create-admin', ['email' => 'taken@bfcgroup.test'])
        ->expectsQuestion('Password (at least 8 characters)', 'short')
        ->expectsOutputToContain('The email has already been taken.')
        ->assertFailed();

    expect(User::where('role', Role::Admin)->count())->toBe(0);
});
