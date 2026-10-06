<?php

use App\Enums\CarStatus;
use App\Enums\Role;
use App\Models\Car;
use App\Models\User;
use App\Notifications\CarDeadlineReminder;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    Notification::fake();
    Carbon::setTestNow('2026-10-06 07:00:00');
    $this->responder = User::factory()->role(Role::Responder, 'PFC')->create();
});

it('reminds whoever must act on CARs due tomorrow or already overdue', function () {
    $overdue = Car::factory()->forFarm('PFC')->status(CarStatus::AwaitingImplementation)->create(['implementation_due_on' => '2026-10-05']);
    $dueTomorrow = Car::factory()->forFarm('PFC')->status(CarStatus::AwaitingResponder)->create(['response_due_on' => '2026-10-07']);

    $this->artisan('cars:send-reminders')->expectsOutput('Sent 2 reminders.')->assertSuccessful();

    Notification::assertSentTo($this->responder, CarDeadlineReminder::class, fn ($reminder) => $reminder->car->is($overdue) && $reminder->isOverdue);
    Notification::assertSentTo($this->responder, CarDeadlineReminder::class, fn ($reminder) => $reminder->car->is($dueTomorrow) && ! $reminder->isOverdue);
});

it('leaves CARs with time to spare and finished CARs alone', function () {
    Car::factory()->forFarm('PFC')->status(CarStatus::AwaitingResponder)->create(['response_due_on' => '2026-10-09']);
    Car::factory()->forFarm('PFC')->status(CarStatus::ClosedAccepted)->create(['implementation_due_on' => '2026-09-01']);

    $this->artisan('cars:send-reminders')->expectsOutput('Sent 0 reminders.');

    Notification::assertNothingSent();
});

it('is scheduled every weekday morning', function () {
    $event = collect(app(Schedule::class)->events())
        ->first(fn ($event) => str_contains($event->command, 'cars:send-reminders'));

    expect($event?->expression)->toBe('0 7 * * 1-5');
});
