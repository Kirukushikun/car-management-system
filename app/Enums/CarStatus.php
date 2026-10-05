<?php

namespace App\Enums;

/**
 * Where a CAR is in the workflow. The single source of truth for "what happens next" —
 * only CarWorkflow changes it.
 */
enum CarStatus: string
{
    case AwaitingRelease = 'awaiting_release';
    case ReturnedToRequestor = 'returned_to_requestor';
    case AwaitingResponder = 'awaiting_responder';
    case ReturnedToResponder = 'returned_to_responder';
    case AwaitingResponderApproval = 'awaiting_responder_approval';
    case AwaitingImplementation = 'awaiting_implementation';
    case AwaitingEffectivenessCheck = 'awaiting_effectiveness_check';
    case AwaitingRequestorApproval = 'awaiting_requestor_approval';
    case ClosedAccepted = 'closed_accepted';
    case OpenNotAccepted = 'open_not_accepted';
    case Voided = 'voided';

    public function label(): string
    {
        return match ($this) {
            self::AwaitingRelease => 'Awaiting Release',
            self::ReturnedToRequestor => 'Returned to Requestor',
            self::AwaitingResponder => 'Awaiting Responder',
            self::ReturnedToResponder => 'Returned to Responder',
            self::AwaitingResponderApproval => 'Awaiting Responder Approval',
            self::AwaitingImplementation => 'Awaiting Implementation',
            self::AwaitingEffectivenessCheck => 'Awaiting Effectiveness Check',
            self::AwaitingRequestorApproval => 'Awaiting Requestor Approval',
            self::ClosedAccepted => 'Closed — Accepted',
            self::OpenNotAccepted => 'Open — Not Accepted',
            self::Voided => 'Voided',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::AwaitingResponder, self::AwaitingImplementation => 'blue',
            self::AwaitingRelease, self::AwaitingResponderApproval, self::AwaitingRequestorApproval => 'violet',
            self::AwaitingEffectivenessCheck => 'amber',
            self::ReturnedToRequestor, self::ReturnedToResponder, self::OpenNotAccepted => 'red',
            self::ClosedAccepted => 'green',
            self::Voided => 'slate',
        };
    }

    /**
     * Requirement phase the CAR is in: 1 initiation, 2 response, 3 closure. Null once finished.
     */
    public function phase(): ?int
    {
        return match ($this) {
            self::AwaitingRelease, self::ReturnedToRequestor, self::AwaitingResponder => 1,
            self::AwaitingResponderApproval, self::ReturnedToResponder => 2,
            self::AwaitingImplementation, self::AwaitingEffectivenessCheck,
            self::AwaitingRequestorApproval, self::OpenNotAccepted => 3,
            self::ClosedAccepted, self::Voided => null,
        };
    }

    /**
     * The role that must act next; null when the CAR is finished.
     */
    public function ownerRole(): ?Role
    {
        return match ($this) {
            self::AwaitingRelease, self::AwaitingRequestorApproval => Role::RequestorApprover,
            self::ReturnedToRequestor => Role::Requestor,
            self::AwaitingResponder, self::ReturnedToResponder,
            self::AwaitingImplementation, self::OpenNotAccepted => Role::Responder,
            self::AwaitingResponderApproval, self::AwaitingEffectivenessCheck => Role::ResponderApprover,
            self::ClosedAccepted, self::Voided => null,
        };
    }

    public function isOpen(): bool
    {
        return ! in_array($this, [self::ClosedAccepted, self::Voided], true);
    }

    /**
     * @return list<self>
     */
    public static function open(): array
    {
        return array_values(array_filter(self::cases(), fn (self $status): bool => $status->isOpen()));
    }
}
