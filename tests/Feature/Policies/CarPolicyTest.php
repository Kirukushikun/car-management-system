<?php

use App\Enums\CarAction;
use App\Enums\CarStatus;
use App\Enums\Role;
use App\Models\Car;
use App\Models\User;

test('every active user may view CARs', function (Role $role) {
    $user = User::factory()->role($role)->create();
    $car = Car::factory()->create();

    expect($user->can('viewAny', Car::class))->toBeTrue()
        ->and($user->can('view', $car))->toBeTrue();
})->with(Role::cases());

test('deactivated users may not view CARs', function () {
    $user = User::factory()->inactive()->create();

    expect($user->can('viewAny', Car::class))->toBeFalse()
        ->and($user->can('view', Car::factory()->create()))->toBeFalse();
});

test('only requestors may create CARs', function (Role $role, bool $allowed) {
    expect(User::factory()->role($role)->create()->can('create', Car::class))->toBe($allowed);
})->with([
    [Role::Requestor, true],
    [Role::RequestorApprover, false],
    [Role::Responder, false],
    [Role::ResponderApprover, false],
    [Role::Monitor, false],
    [Role::Admin, false],
]);

test('workflow actions follow the transition table', function () {
    $car = Car::factory()->status(CarStatus::AwaitingRelease)->create();
    $approver = User::factory()->role(Role::RequestorApprover)->create();
    $responder = User::factory()->role(Role::Responder)->create(['farm_id' => $car->farm_id]);

    expect($approver->can('act', [$car, CarAction::Release]))->toBeTrue()
        ->and($responder->can('act', [$car, CarAction::Release]))->toBeFalse();
});
