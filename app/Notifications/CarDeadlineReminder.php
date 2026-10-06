<?php

namespace App\Notifications;

use App\Models\Car;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Daily reminder to whoever must act on a CAR whose active deadline is tomorrow or has passed.
 */
class CarDeadlineReminder extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Car $car,
        public bool $isOverdue,
    ) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    /**
     * @return array<string, string>
     */
    public function viaConnections(): array
    {
        return ['database' => 'sync'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("[{$this->car->reference}] {$this->headline()}")
            ->greeting("Hello {$notifiable->name},")
            ->line("{$this->car->reference} — {$this->car->subcategory->name} ({$this->car->farm->name})")
            ->line("Status: {$this->car->status->label()}. Deadline: {$this->car->activeDueOn()->format('M j, Y')}.")
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
            'status' => $this->car->status->value,
            'message' => $this->headline(),
            'detail' => "{$this->car->status->label()} — deadline {$this->car->activeDueOn()->format('M j, Y')}.",
            'overdue' => $this->isOverdue,
        ];
    }

    private function headline(): string
    {
        return $this->isOverdue ? 'Overdue — this CAR is past its deadline' : 'Due tomorrow — this CAR is waiting on you';
    }
}
