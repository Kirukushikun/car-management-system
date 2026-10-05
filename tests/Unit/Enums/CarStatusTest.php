<?php

use App\Enums\CarStatus;
use App\Enums\Role;

test('each status belongs to a phase and an owner role', function (CarStatus $status, ?int $phase, ?Role $owner) {
    expect($status->phase())->toBe($phase)
        ->and($status->ownerRole())->toBe($owner)
        ->and($status->isOpen())->toBe($phase !== null);
})->with([
    [CarStatus::AwaitingRelease, 1, Role::RequestorApprover],
    [CarStatus::ReturnedToRequestor, 1, Role::Requestor],
    [CarStatus::AwaitingResponder, 1, Role::Responder],
    [CarStatus::ReturnedToResponder, 2, Role::Responder],
    [CarStatus::AwaitingResponderApproval, 2, Role::ResponderApprover],
    [CarStatus::AwaitingImplementation, 3, Role::Responder],
    [CarStatus::AwaitingEffectivenessCheck, 3, Role::ResponderApprover],
    [CarStatus::AwaitingRequestorApproval, 3, Role::RequestorApprover],
    [CarStatus::OpenNotAccepted, 3, Role::Responder],
    [CarStatus::ClosedAccepted, null, null],
    [CarStatus::Voided, null, null],
]);
