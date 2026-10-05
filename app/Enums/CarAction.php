<?php

namespace App\Enums;

/**
 * Something a user does to a CAR. Each action is a row of the CarWorkflow transition table
 * and is recorded as one car_events row.
 */
enum CarAction: string
{
    case Submit = 'submit';
    case Release = 'release';
    case Reject = 'reject';
    case Resubmit = 'resubmit';
    case SubmitResponse = 'submit_response';
    case ApproveResponse = 'approve_response';
    case ReturnResponse = 'return_response';
    case UploadEvidence = 'upload_evidence';
    case MarkEffective = 'mark_effective';
    case MarkNotEffective = 'mark_not_effective';
    case Accept = 'accept';
    case NotAccept = 'not_accept';
    case Void = 'void';

    /**
     * Button label shown to the user who can take the action.
     */
    public function label(): string
    {
        return match ($this) {
            self::Submit => 'Submit CAR',
            self::Release => 'Approve & release',
            self::Reject => 'Reject CAR',
            self::Resubmit => 'Resubmit for release',
            self::SubmitResponse => 'Submit response',
            self::ApproveResponse => 'Approve',
            self::ReturnResponse => 'Return for revision',
            self::UploadEvidence => 'Upload implementation evidence',
            self::MarkEffective => 'Mark effective',
            self::MarkNotEffective => 'Not effective',
            self::Accept => 'Accept — close CAR',
            self::NotAccept => 'Not accepted',
            self::Void => 'Void CAR',
        };
    }

    /**
     * Past-tense line for the CAR history.
     */
    public function pastTense(): string
    {
        return match ($this) {
            self::Submit => 'CAR issued and submitted for release',
            self::Release => 'CAR approved and released to the Responder',
            self::Reject => 'CAR rejected before release — returned to the Requestor',
            self::Resubmit => 'CAR updated and resubmitted for release',
            self::SubmitResponse => 'Containment, root cause and corrective action submitted for approval',
            self::ApproveResponse => 'Root cause & corrective action approved — routed to Phase III',
            self::ReturnResponse => 'Response returned for revision',
            self::UploadEvidence => 'Implementation evidence uploaded',
            self::MarkEffective => 'Corrective action validated as effective — forwarded for final acceptance',
            self::MarkNotEffective => 'Corrective action not effective — returned to Phase II',
            self::Accept => 'Final acceptance given — CAR closed',
            self::NotAccept => 'Not accepted — new end date set, looped back to implementation',
            self::Void => 'CAR voided',
        };
    }

    /**
     * Actions that must explain themselves: every rejection, return and void.
     */
    public function requiresNote(): bool
    {
        return in_array($this, [self::Reject, self::ReturnResponse, self::MarkNotEffective, self::Void], true);
    }

    public function requiresNewDueDate(): bool
    {
        return $this === self::NotAccept;
    }

    /**
     * Actions that send the CAR back through Phase II or III and so open a new round.
     */
    public function opensNewRound(): bool
    {
        return in_array($this, [self::MarkNotEffective, self::NotAccept], true);
    }
}
