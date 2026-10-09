<?php

namespace App\Notifications;

use App\Enums\CarAction;
use App\Enums\CarStatus;
use App\Enums\Role;
use App\Models\Car;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * The CAR moved and the recipient has something to do (or should know). The in-app flag is
 * written immediately; the email goes through the queue.
 */
class CarNeedsAction extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Car $car,
        public CarAction $action,
        public string $actorName,
    ) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    /**
     * The in-app flag must not wait for a queue worker.
     *
     * @return array<string, string>
     */
    public function viaConnections(): array
    {
        return ['database' => 'sync'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("[{$this->car->reference}] {$this->headline($notifiable)}")
            ->greeting("Hello {$notifiable->name},")
            ->line("{$this->car->reference} — {$this->car->subcategory->name} ({$this->car->farm->name}, {$this->car->issuedToUnit->name})")
            ->line("{$this->action->pastTense()} by {$this->actorName}.")
            ->when($this->note(), fn (MailMessage $mail, string $note) => $mail->line("Remarks: “{$note}”"))
            ->line("Status: {$this->car->status->label()}.")
            ->action('Open the CAR', route('cars.show', $this->car));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'car_id' => $this->car->id,
            'reference' => $this->car->reference,
            'action' => $this->action->value,
            'status' => $this->car->status->value,
            'actor' => $this->actorName,
            'message' => $this->headline($notifiable),
            'detail' => "{$this->action->pastTense()} by {$this->actorName}.",
        ];
    }

    /**
     * The reason or remarks given with this action, if any.
     */
    private function note(): ?string
    {
        return $this->car->events()->where('action', $this->action)->latest('id')->value('note');
    }

    /**
     * Short line telling the recipient why they are hearing about this CAR.
     */
    private function headline(object $notifiable): string
    {
        $isNextActor = $notifiable instanceof User && $this->car->status->ownerRole() !== null
            && in_array($notifiable->role, [$this->car->status->ownerRole(), ...$this->extraActorRoles()], true);

        if (! $isNextActor) {
            return match ($this->action) {
                CarAction::Accept => 'Your CAR was accepted and closed',
                CarAction::Void => 'A CAR you filed was voided',
                CarAction::NotAccept => 'Not accepted — the Responder will propose a new solution',
                default => $this->car->status->label(),
            };
        }

        return match ($this->car->status) {
            CarStatus::AwaitingRelease => 'A new CAR is waiting for your release',
            CarStatus::ReturnedToRequestor => 'Your CAR was returned — please correct and resubmit',
            CarStatus::AwaitingResponder => 'A CAR was issued to your farm — response needed',
            CarStatus::ReturnedToResponder => match ($this->action) {
                CarAction::NotAccept => 'Not accepted — propose a new solution',
                CarAction::MarkNotEffective => 'Not effective — propose a new solution',
                default => 'Your response needs revision',
            },
            CarStatus::AwaitingResponderApproval => 'A response is waiting for your approval',
            CarStatus::AwaitingImplementation => 'Corrective actions approved — upload the implementation evidence',
            CarStatus::AwaitingEffectivenessCheck => 'Evidence uploaded — check whether the action was effective',
            CarStatus::AwaitingRequestorApproval => 'A CAR is waiting for your final acceptance',
            CarStatus::OpenNotAccepted => 'Not accepted — re-implement and upload new evidence',
            default => $this->car->status->label(),
        };
    }

    /**
     * Roles that may also act on the current status besides its owner (a Responder Approver can
     * prepare the response).
     *
     * @return list<Role>
     */
    private function extraActorRoles(): array
    {
        return in_array($this->car->status, [CarStatus::AwaitingResponder, CarStatus::ReturnedToResponder], true)
            ? [Role::ResponderApprover]
            : [];
    }
}
