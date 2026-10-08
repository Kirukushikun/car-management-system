<?php

use App\Enums\Role;
use App\Models\User;

it('grants an active IT Admin under their central user id', function () {
    $this->artisan('app:create-admin', ['id' => 4821, 'email' => 'it@bfcgroup.test', 'name' => 'IT Department'])
        ->assertSuccessful();

    expect(User::findOrFail(4821))
        ->email->toBe('it@bfcgroup.test')
        ->role->toBe(Role::Admin)
        ->is_active->toBeTrue()
        ->is_sample->toBeFalse();
});

it('refuses an id or email that already has access', function () {
    User::factory()->create(['email' => 'taken@bfcgroup.test']);

    $this->artisan('app:create-admin', ['id' => 'abc', 'email' => 'taken@bfcgroup.test'])
        ->expectsOutputToContain('The email has already been taken.')
        ->expectsOutputToContain('The id field must be an integer.')
        ->assertFailed();

    expect(User::where('role', Role::Admin)->count())->toBe(0);
});
