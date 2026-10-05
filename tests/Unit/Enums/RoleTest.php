<?php

use App\Enums\Role;

test('each role escalates to the expected approver role', function (Role $role, ?Role $approverRole) {
    expect($role->approverRole())->toBe($approverRole);
})->with([
    [Role::Requestor, Role::RequestorApprover],
    [Role::RequestorApprover, Role::RequestorApprover],
    [Role::Responder, Role::ResponderApprover],
    [Role::ResponderApprover, Role::ResponderApprover],
    [Role::Monitor, null],
    [Role::Admin, null],
]);

test('only responder roles are tied to a farm', function (Role $role, bool $isFarmScoped) {
    expect($role->isFarmScoped())->toBe($isFarmScoped);
})->with([
    [Role::Requestor, false],
    [Role::RequestorApprover, false],
    [Role::Responder, true],
    [Role::ResponderApprover, true],
    [Role::Monitor, false],
    [Role::Admin, false],
]);
