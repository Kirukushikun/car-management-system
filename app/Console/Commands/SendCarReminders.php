<?php

namespace App\Console\Commands;

use App\Models\Car;
use App\Notifications\CarDeadlineReminder;
use App\Services\CarRecipients;
use Carbon\CarbonImmutable;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Notification;

/**
 * Reminds the next actors on every open CAR whose active deadline is tomorrow or already past.
 * Scheduled daily (routes/console.php).
 */
#[Signature('cars:send-reminders')]
#[Description('Remind whoever must act on CARs that are due tomorrow or overdue')]
class SendCarReminders extends Command
{
    public function handle(CarRecipients $recipients): int
    {
        $today = CarbonImmutable::today();
        $sent = 0;

        Car::open()->with(['farm', 'issuedToUnit', 'subcategory'])->get()
            ->filter(fn (Car $car): bool => $car->activeDueOn() !== null && $car->activeDueOn()->lte($today->addDay()))
            ->each(function (Car $car) use ($recipients, $today, &$sent): void {
                $users = $recipients->nextActors($car);

                if ($users->isNotEmpty()) {
                    Notification::send($users, new CarDeadlineReminder($car, $car->activeDueOn()->lt($today)));
                    $sent += $users->count();
                }
            });

        $this->info("Sent {$sent} ".str('reminder')->plural($sent).'.');

        return self::SUCCESS;
    }
}
