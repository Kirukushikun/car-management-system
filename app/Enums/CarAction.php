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
            self::NotAccept => 'Not accepted — sent back to the Responder for a new solution',
            self::Void => 'CAR voided',
        };
    }

    /**
     * What the confirmation dialog asks before the action runs — who the CAR goes to next.
     */
    public function confirmation(): string
    {
        return match ($this) {
            self::Submit => 'The CAR gets its reference number and goes to the Requestor Approver for review.',
            self::Release => 'The CAR is released to the responding farm and the response deadline starts.',
            self::Reject => 'The CAR goes back to the Requestor with your reason.',
            self::Resubmit => 'The corrected CAR goes back to the Requestor Approver for release.',
            self::SubmitResponse => 'Your containment, root cause and corrective actions go to the Responder Approver. You cannot edit them unless they are returned.',
            self::ApproveResponse => 'The corrective actions are approved and the Responder moves on to implementation (Phase III).',
            self::ReturnResponse => 'The response goes back to the Responder for revision with your reason.',
            self::UploadEvidence => 'The evidence goes to the Responder Approver for the effectiveness check. You cannot add files to this round afterwards.',
            self::MarkEffective => 'The CAR goes to the Requestor Approver for final acceptance.',
            self::MarkNotEffective => 'The CAR goes back to Phase II and the Responder must revise the response.',
            self::Accept => 'The CAR is closed with your remarks. This cannot be undone.',
            self::NotAccept => 'The CAR goes back to the Responder with your reason to propose a new solution by the new end date. The Responder Approver approves it again before it is implemented.',
            self::Void => 'The CAR is voided and leaves every queue. This cannot be undone.',
        };
    }

    /**
     * Sends the CAR backwards or ends it — confirmed with a red button.
     */
    public function isNegative(): bool
    {
        return $this->requiresNote();
    }

    /**
     * Actions that must explain themselves: every rejection, return, non-acceptance and void.
     */
    public function requiresNote(): bool
    {
        return in_array($this, [self::Reject, self::ReturnResponse, self::MarkNotEffective, self::NotAccept, self::Void], true);
    }

    /**
     * Actions that take a note: the required ones, plus optional remarks when accepting.
     */
    public function allowsNote(): bool
    {
        return $this->requiresNote() || $this === self::Accept;
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
