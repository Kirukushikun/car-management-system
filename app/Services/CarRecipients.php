<?php

namespace App\Services;

use App\Enums\CarAction;
use App\Enums\Role;
use App\Models\Car;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

/**
 * Who should be flagged about a CAR. "Next actors" are the active users the workflow would
 * let act on it now (voiding aside); a few steps also inform people who are not acting next.
 */
class CarRecipients
{
    public function __construct(private CarWorkflow $workflow) {}

    /**
     * Everyone who can move the CAR forward from its current status.
     *
     * @return Collection<int, User>
     */
    public function nextActors(Car $car): Collection
    {
        $roles = collect(CarWorkflow::transitions())
            ->filter(fn (array $transition): bool => in_array($car->status, $transition['from'], true) && $transition['action'] !== CarAction::Void)
            ->flatMap(fn (array $transition): array => $transition['roles'])
            ->unique()
            ->values();

        if ($roles->isEmpty()) {
            return new Collection;
        }

        return User::active()
            ->whereIn('role', $roles->all())
            ->get()
            ->filter(fn (User $user): bool => collect($this->workflow->availableActions($car, $user))->contains(fn (CarAction $action): bool => $action !== CarAction::Void))
            ->values();
    }

    /**
     * Who to flag after an action: the next actors, plus people the requirement says must also
     * hear about it, minus whoever just acted.
     *
     * - Not accepted (Step 13): the farm's Responder Approvers as well as its Responders.
     * - Closed or voided: the Requestor who filed the CAR.
     *
     * @return Collection<int, User>
     */
    public function afterAction(Car $car, CarAction $action, User $actor): Collection
    {
        $recipients = $this->nextActors($car);

        if ($action === CarAction::NotAccept) {
            $recipients = $recipients->merge(
                User::active()->where('role', Role::ResponderApprover)->where('farm_id', $car->farm_id)->get()
            );
        }

        if (in_array($action, [CarAction::Accept, CarAction::Void], true) && $car->requestor?->is_active) {
            $recipients->push($car->requestor);
        }

        return $recipients->unique('id')->reject(fn (User $user): bool => $user->is($actor))->values();
    }
}
