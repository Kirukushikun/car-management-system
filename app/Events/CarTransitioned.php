<?php

namespace App\Events;

use App\Enums\CarAction;
use App\Models\Car;
use App\Models\User;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * A CAR moved through the workflow. Dispatched after the transaction commits; the notification
 * listeners (Phase 6) hang off this event.
 */
class CarTransitioned implements ShouldDispatchAfterCommit
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Car $car,
        public CarAction $action,
        public User $actor,
    ) {}
}
