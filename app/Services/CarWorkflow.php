<?php

namespace App\Services;

use App\Enums\CarAction;
use App\Enums\CarStatus;
use App\Enums\ComplaintType;
use App\Enums\Role;
use App\Events\CarTransitioned;
use App\Models\Car;
use App\Models\CarEvent;
use App\Models\Category;
use App\Models\IssuedToUnit;
use App\Models\Subcategory;
use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

/**
 * The CAR state machine — the only code allowed to change a CAR's status.
 *
 * Every allowed move is one row of transitions(). Each move is checked against the acting
 * user's role (and farm, for Responder roles), applied inside a transaction with the CAR row
 * locked, recorded as a car_events row, and announced with CarTransitioned after commit.
 */
class CarWorkflow
{
    public function __construct(
        private CarNumberGenerator $numbers,
        private DeadlineCalculator $deadlines,
    ) {}

    /**
     * The transition table: from which statuses, which action, by which role, to which status.
     *
     * @return list<array{from: list<CarStatus>, action: CarAction, role: Role, to: CarStatus}>
     */
    public static function transitions(): array
    {
        return [
            ['from' => [CarStatus::AwaitingRelease], 'action' => CarAction::Release, 'role' => Role::RequestorApprover, 'to' => CarStatus::AwaitingResponder],
            ['from' => [CarStatus::AwaitingRelease], 'action' => CarAction::Reject, 'role' => Role::RequestorApprover, 'to' => CarStatus::ReturnedToRequestor],
            ['from' => [CarStatus::ReturnedToRequestor], 'action' => CarAction::Resubmit, 'role' => Role::Requestor, 'to' => CarStatus::AwaitingRelease],
            ['from' => [CarStatus::AwaitingResponder, CarStatus::ReturnedToResponder], 'action' => CarAction::SubmitResponse, 'role' => Role::Responder, 'to' => CarStatus::AwaitingResponderApproval],
            ['from' => [CarStatus::AwaitingResponderApproval], 'action' => CarAction::ApproveResponse, 'role' => Role::ResponderApprover, 'to' => CarStatus::AwaitingImplementation],
            ['from' => [CarStatus::AwaitingResponderApproval], 'action' => CarAction::ReturnResponse, 'role' => Role::ResponderApprover, 'to' => CarStatus::ReturnedToResponder],
            ['from' => [CarStatus::AwaitingImplementation, CarStatus::OpenNotAccepted], 'action' => CarAction::UploadEvidence, 'role' => Role::Responder, 'to' => CarStatus::AwaitingEffectivenessCheck],
            ['from' => [CarStatus::AwaitingEffectivenessCheck], 'action' => CarAction::MarkEffective, 'role' => Role::ResponderApprover, 'to' => CarStatus::AwaitingRequestorApproval],
            ['from' => [CarStatus::AwaitingEffectivenessCheck], 'action' => CarAction::MarkNotEffective, 'role' => Role::ResponderApprover, 'to' => CarStatus::ReturnedToResponder],
            ['from' => [CarStatus::AwaitingRequestorApproval], 'action' => CarAction::Accept, 'role' => Role::RequestorApprover, 'to' => CarStatus::ClosedAccepted],
            ['from' => [CarStatus::AwaitingRequestorApproval], 'action' => CarAction::NotAccept, 'role' => Role::RequestorApprover, 'to' => CarStatus::OpenNotAccepted],
            ['from' => CarStatus::open(), 'action' => CarAction::Void, 'role' => Role::Admin, 'to' => CarStatus::Voided],
        ];
    }

    public function canSubmit(User $user): bool
    {
        return $user->is_active && $user->role === Role::Requestor;
    }

    /**
     * Issue a new CAR (Phase I, Steps 1–6): reference number, snapshotted deadlines, round 1,
     * and the first history entry. It starts in "Awaiting Release".
     *
     * @param  array{farm_id: int, issued_to_unit_id: int, category_id: int, subcategory_id: int, issued_by: string, complainant: string, complaint_type: ComplaintType|string, problem_details: string, complaint_received_on?: ?string}  $details
     *
     * @throws AuthorizationException when the user may not file CARs
     * @throws ValidationException when the unit, category and sub-category do not belong together
     */
    public function submit(User $requestor, array $details): Car
    {
        if (! $this->canSubmit($requestor)) {
            throw new AuthorizationException('Only an active Requestor can file a CAR.');
        }

        $car = DB::transaction(function () use ($requestor, $details): Car {
            [$category] = $this->resolveClassification($details);
            $issuedOn = CarbonImmutable::today();

            $car = Car::create([
                ...$details,
                ...$this->deadlines->forCategory($category, $issuedOn),
                'reference' => $this->numbers->next($issuedOn),
                'status' => CarStatus::AwaitingRelease,
                'current_round' => 1,
                'requestor_id' => $requestor->id,
                'issued_on' => $issuedOn,
            ]);

            $car->rounds()->create(['number' => 1, 'opened_by_action' => CarAction::Submit]);
            $this->record($car, $requestor, CarAction::Submit, null, null);

            return $car;
        });

        CarTransitioned::dispatch($car, CarAction::Submit, $requestor);

        return $car;
    }

    /**
     * A returned CAR goes back for release with its corrected Phase I details. The issued date
     * stays; deadlines are recalculated from it in case the category changed.
     *
     * @param  array{farm_id: int, issued_to_unit_id: int, category_id: int, subcategory_id: int, issued_by: string, complainant: string, complaint_type: ComplaintType|string, problem_details: string, complaint_received_on?: ?string}  $details
     *
     * @throws AuthorizationException when the user may not resubmit this CAR
     * @throws ValidationException when the unit, category and sub-category do not belong together
     */
    public function resubmit(Car $car, User $user, array $details): Car
    {
        return DB::transaction(function () use ($car, $user, $details): Car {
            if (! $this->can($car, $user, CarAction::Resubmit)) {
                throw new AuthorizationException("You cannot resubmit {$car->reference}.");
            }

            [$category] = $this->resolveClassification($details);

            $car->update([...$details, ...$this->deadlines->forCategory($category, $car->issued_on)]);

            return $this->apply($car, $user, CarAction::Resubmit);
        });
    }

    /**
     * Actions the user may take on the CAR right now, in transition-table order.
     *
     * @return list<CarAction>
     */
    public function availableActions(Car $car, User $user): array
    {
        return array_values(array_filter(
            array_map(fn (array $transition): CarAction => $transition['action'], self::transitions()),
            fn (CarAction $action): bool => $this->can($car, $user, $action),
        ));
    }

    public function can(Car $car, User $user, CarAction $action): bool
    {
        $transition = $this->transitionFor($car->status, $action);

        if ($transition === null || ! $user->is_active || $user->role !== $transition['role']) {
            return false;
        }

        if ($user->role->isFarmScoped() && $user->farm_id !== $car->farm_id) {
            return false;
        }

        if ($action === CarAction::Resubmit && $car->requestor_id !== $user->id) {
            return false;
        }

        return true;
    }

    /**
     * Move the CAR along one transition.
     *
     * @throws AuthorizationException when the action is not allowed for this user and status
     * @throws ValidationException when a required reason or new end date is missing or invalid
     */
    public function apply(Car $car, User $user, CarAction $action, ?string $note = null, ?CarbonInterface $newDueOn = null): Car
    {
        if ($action === CarAction::Submit) {
            throw new InvalidArgumentException('New CARs are filed with submit(), not apply().');
        }

        $note = $note !== null ? trim($note) : null;
        $this->validateInput($action, $note, $newDueOn);

        $updated = DB::transaction(function () use ($car, $user, $action, $note, $newDueOn): Car {
            $locked = Car::whereKey($car->getKey())->lockForUpdate()->firstOrFail();

            if (! $this->can($locked, $user, $action)) {
                throw new AuthorizationException("A {$user->role->label()} cannot \"{$action->label()}\" {$locked->reference} while it is {$locked->status->label()}.");
            }

            $from = $locked->status;
            $round = $locked->current_round;
            $locked->status = $this->transitionFor($from, $action)['to'];

            match ($action) {
                CarAction::Release => $locked->released_at = now(),
                CarAction::Accept => $locked->closed_at = now(),
                CarAction::Void => $locked->voided_at = now(),
                CarAction::NotAccept => $locked->revised_due_on = CarbonImmutable::instance($newDueOn)->startOfDay(),
                default => null,
            };

            if ($action->opensNewRound()) {
                $locked->current_round = $round + 1;
                $locked->rounds()->create([
                    'number' => $locked->current_round,
                    'opened_by_action' => $action,
                    'due_on' => $action === CarAction::NotAccept ? $locked->revised_due_on : null,
                ]);
            }

            $locked->save();
            $this->record($locked, $user, $action, $from, $note, $round);

            return $locked;
        });

        $car->setRawAttributes($updated->getAttributes(), true);
        CarTransitioned::dispatch($car, $action, $user);

        return $car;
    }

    /**
     * @return array{from: list<CarStatus>, action: CarAction, role: Role, to: CarStatus}|null
     */
    private function transitionFor(CarStatus $from, CarAction $action): ?array
    {
        foreach (self::transitions() as $transition) {
            if ($transition['action'] === $action && in_array($from, $transition['from'], true)) {
                return $transition;
            }
        }

        return null;
    }

    private function validateInput(CarAction $action, ?string $note, ?CarbonInterface $newDueOn): void
    {
        if ($action->requiresNote() && ($note === null || $note === '')) {
            throw ValidationException::withMessages(['note' => "Give a reason for \"{$action->label()}\"."]);
        }

        if ($action->requiresNewDueDate() && ($newDueOn === null || ! $newDueOn->isAfter(CarbonImmutable::today()))) {
            throw ValidationException::withMessages(['new_due_on' => 'The new end date must be after today.']);
        }
    }

    /**
     * Checks that the sub-category belongs to the category and the category to the unit's line.
     *
     * @param  array<string, mixed>  $details
     * @return array{0: Category, 1: Subcategory, 2: IssuedToUnit}
     */
    private function resolveClassification(array $details): array
    {
        $unit = IssuedToUnit::findOrFail($details['issued_to_unit_id']);
        $category = Category::findOrFail($details['category_id']);
        $subcategory = Subcategory::findOrFail($details['subcategory_id']);

        if ($category->business_line_id !== $unit->business_line_id) {
            throw ValidationException::withMessages(['category_id' => 'The category does not belong to the selected unit\'s business line.']);
        }

        if ($subcategory->category_id !== $category->id) {
            throw ValidationException::withMessages(['subcategory_id' => 'The sub-category does not belong to the selected category.']);
        }

        return [$category, $subcategory, $unit];
    }

    private function record(Car $car, User $actor, CarAction $action, ?CarStatus $from, ?string $note, ?int $round = null): void
    {
        CarEvent::create([
            'car_id' => $car->id,
            'round' => $round ?? $car->current_round,
            'action' => $action,
            'from_status' => $from,
            'to_status' => $car->status,
            'actor_id' => $actor->id,
            'note' => $note,
            'created_at' => now(),
        ]);
    }
}
