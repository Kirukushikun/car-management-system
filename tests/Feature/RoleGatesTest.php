<?php

use App\Enums\Role;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

/**
 * Full role × ability matrix for the area gates defined in AppServiceProvider.
 *
 * @return array<string, list<Role>>
 */
function rolesAllowedPerAbility(): array
{
    return [
        'view-dashboard' => [Role::Monitor],
        'create-cars' => [Role::Requestor],
        'view-queue' => [Role::Requestor, Role::RequestorApprover, Role::Responder, Role::ResponderApprover],
        'view-overdue' => [Role::ResponderApprover, Role::Monitor, Role::Admin],
        'administer' => [Role::Admin],
    ];
}

test('each role is granted exactly the abilities in the matrix', function (Role $role) {
    $user = User::factory()->role($role)->make();

    foreach (rolesAllowedPerAbility() as $ability => $allowedRoles) {
        expect(Gate::forUser($user)->allows($ability))
            ->toBe(in_array($role, $allowedRoles, true), "{$role->label()} → {$ability}");
    }
})->with(Role::cases());

test('every role home page is a page that role may open', function (Role $role) {
    $user = User::factory()->role($role)->create();

    $this->actingAs($user)->get($role->homeUrl())->assertOk();
})->with(Role::cases());

test('every sidebar link opens for the role that sees it', function (Role $role) {
    $user = User::factory()->role($role)->create();

    foreach ($role->navigation() as $item) {
        $this->actingAs($user)->get(route($item['route'], $item['params']))->assertOk();
    }
})->with(Role::cases());
