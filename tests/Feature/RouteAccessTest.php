<?php

use App\Enums\Role;
use App\Models\User;

dataset('protected pages', [
    'dashboard' => ['/dashboard'],
    'all cars' => ['/cars'],
    'new car' => ['/cars/create'],
    'car detail' => ['/cars/CAR-2026-0147'],
    'users' => ['/admin/users'],
    'matrix' => ['/admin/matrix'],
]);

it('sends guests to sign in', function (string $path) {
    $this->get($path)->assertRedirect(route('login'));
})->with('protected pages');

it('forbids pages outside the signed-in role', function (Role $role, string $path) {
    $this->actingAs(User::factory()->role($role)->create())->get($path)->assertForbidden();
})->with([
    'requestor on dashboard' => [Role::Requestor, '/dashboard'],
    'monitor on new car' => [Role::Monitor, '/cars/create'],
    'responder approver on users' => [Role::ResponderApprover, '/admin/users'],
    'monitor on matrix' => [Role::Monitor, '/admin/matrix'],
    'monitor on my queue' => [Role::Monitor, '/cars?view=mine'],
    'requestor on overdue' => [Role::Requestor, '/cars?view=overdue'],
]);
