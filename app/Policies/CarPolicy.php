<?php

namespace App\Policies;

use App\Enums\CarAction;
use App\Models\Car;
use App\Models\User;
use App\Services\CarWorkflow;

/**
 * Per-CAR authorization. Every entry point (Livewire action, route, download, print, export)
 * asks this policy; "what may happen next" is delegated to the CarWorkflow transition table.
 */
class CarPolicy
{
    public function __construct(private CarWorkflow $workflow) {}

    /**
     * Every active user may list CARs — assumption until open decision #5 (visibility) is settled.
     */
    public function viewAny(User $user): bool
    {
        return $user->is_active;
    }

    /**
     * Same visibility assumption as viewAny: every active user may open any CAR.
     */
    public function view(User $user, Car $car): bool
    {
        return $user->is_active;
    }

    public function create(User $user): bool
    {
        return $this->workflow->canSubmit($user);
    }

    /**
     * May the user take this workflow action on this CAR right now?
     */
    public function act(User $user, Car $car, CarAction $action): bool
    {
        return $this->workflow->can($car, $user, $action);
    }
}
