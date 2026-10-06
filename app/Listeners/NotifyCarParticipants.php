<?php

namespace App\Listeners;

use App\Events\CarTransitioned;
use App\Notifications\CarNeedsAction;
use App\Services\CarRecipients;
use Illuminate\Support\Facades\Notification;

/**
 * Flags the people who must act next whenever a CAR moves (requirement Steps 6, 10, 12, 13).
 */
class NotifyCarParticipants
{
    public function __construct(private CarRecipients $recipients) {}

    public function handle(CarTransitioned $event): void
    {
        $car = $event->car->fresh(['farm', 'issuedToUnit', 'subcategory', 'requestor']);
        $recipients = $this->recipients->afterAction($car, $event->action, $event->actor);

        if ($recipients->isNotEmpty()) {
            Notification::send($recipients, new CarNeedsAction($car, $event->action, $event->actor->name));
        }
    }
}
