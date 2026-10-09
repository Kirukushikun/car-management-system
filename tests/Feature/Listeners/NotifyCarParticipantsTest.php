<?php

use App\Enums\CarAction;
use App\Enums\CarStatus;
use App\Enums\Role;
use App\Models\Car;
use App\Models\User;
use App\Notifications\CarNeedsAction;
use App\Services\CarWorkflow;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    Notification::fake();
    $this->workflow = app(CarWorkflow::class);
    $this->pfcResponder = User::factory()->role(Role::Responder, 'PFC')->create();
    $this->pfcApprover = User::factory()->role(Role::ResponderApprover, 'PFC')->create();
    $this->hatcheryResponder = User::factory()->role(Role::Responder, 'HATCHERY')->create();
    $this->requestorApprover = User::factory()->role(Role::RequestorApprover)->create();
});

it('flags the farm\'s responders when a CAR is released', function () {
    $car = Car::factory()->forFarm('PFC')->status(CarStatus::AwaitingRelease)->create();

    $this->workflow->apply($car, $this->requestorApprover, CarAction::Release);

    Notification::assertSentTo([$this->pfcResponder, $this->pfcApprover], CarNeedsAction::class,
        fn (CarNeedsAction $notification): bool => $notification->action === CarAction::Release && $notification->car->is($car));
    Notification::assertNotSentTo([$this->hatcheryResponder, $this->requestorApprover], CarNeedsAction::class);
});

it('flags the requestor approvers when a CAR is submitted', function () {
    $requestor = User::factory()->role(Role::Requestor)->create();
    $car = Car::factory()->status(CarStatus::ReturnedToRequestor)->create(['requestor_id' => $requestor->id]);

    $this->workflow->apply($car, $requestor, CarAction::Resubmit);

    Notification::assertSentTo($this->requestorApprover, CarNeedsAction::class);
    Notification::assertNotSentTo($requestor, CarNeedsAction::class);
});

it('flags the filing requestor when a CAR is rejected', function () {
    $car = Car::factory()->status(CarStatus::AwaitingRelease)->create();

    $this->workflow->apply($car, $this->requestorApprover, CarAction::Reject, note: 'Unclear.');

    Notification::assertSentTo($car->requestor, CarNeedsAction::class);
    Notification::assertSentTimes(CarNeedsAction::class, 1);
});

it('flags both the responders and responder approvers when a CAR is not accepted', function () {
    $car = Car::factory()->forFarm('PFC')->status(CarStatus::AwaitingRequestorApproval)->create();

    $this->workflow->apply($car, $this->requestorApprover, CarAction::NotAccept, note: 'Still cracked.', newDueOn: CarbonImmutable::today()->addWeek());

    Notification::assertSentTo([$this->pfcResponder, $this->pfcApprover], CarNeedsAction::class);
    Notification::assertNotSentTo($this->hatcheryResponder, CarNeedsAction::class);
});

it('asks the responder for a new solution and passes on the reason when a CAR is not accepted', function () {
    $car = Car::factory()->forFarm('PFC')->status(CarStatus::AwaitingRequestorApproval)->create();

    $this->workflow->apply($car, $this->requestorApprover, CarAction::NotAccept, note: 'Still receiving cracked eggs.', newDueOn: CarbonImmutable::today()->addWeek());

    Notification::assertSentTo($this->pfcResponder, CarNeedsAction::class, fn (CarNeedsAction $notification): bool => $notification->toArray($this->pfcResponder)['message'] === 'Not accepted — propose a new solution'
        && in_array('Remarks: “Still receiving cracked eggs.”', $notification->toMail($this->pfcResponder)->introLines, true));
});

it('tells the filing requestor when the CAR is closed', function () {
    $car = Car::factory()->status(CarStatus::AwaitingRequestorApproval)->create();

    $this->workflow->apply($car, $this->requestorApprover, CarAction::Accept);

    Notification::assertSentTo($car->requestor, CarNeedsAction::class, function (CarNeedsAction $notification) use ($car): bool {
        return $notification->toArray($car->requestor)['message'] === 'Your CAR was accepted and closed';
    });
});

it('never flags the person who just acted', function () {
    $car = Car::factory()->forFarm('PFC')->status(CarStatus::AwaitingEffectivenessCheck)->create();

    $this->workflow->apply($car, $this->pfcApprover, CarAction::MarkNotEffective, note: 'Still failing.');

    Notification::assertSentTo($this->pfcResponder, CarNeedsAction::class);
    Notification::assertNotSentTo($this->pfcApprover, CarNeedsAction::class);
});

it('skips deactivated users', function () {
    $this->pfcResponder->update(['is_active' => false]);
    $car = Car::factory()->forFarm('PFC')->status(CarStatus::AwaitingRelease)->create();

    $this->workflow->apply($car, $this->requestorApprover, CarAction::Release);

    Notification::assertNotSentTo($this->pfcResponder, CarNeedsAction::class);
});

it('writes an in-app flag and an email telling the recipient what to do', function () {
    $car = Car::factory()->forFarm('PFC')->status(CarStatus::AwaitingResponder)->create();
    $notification = new CarNeedsAction($car->fresh(), CarAction::Release, 'Stephanie Flores');

    expect($notification->via($this->pfcResponder))->toBe(['database', 'mail'])
        ->and($notification->toArray($this->pfcResponder))
        ->toMatchArray(['reference' => $car->reference, 'message' => 'A CAR was issued to your farm — response needed'])
        ->and($notification->toMail($this->pfcResponder)->subject)
        ->toBe("[{$car->reference}] A CAR was issued to your farm — response needed");
});
