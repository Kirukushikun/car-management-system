<?php

use App\Enums\CarAction;
use App\Enums\CarStatus;
use App\Enums\ComplaintType;
use App\Enums\Role;
use App\Events\CarTransitioned;
use App\Models\Car;
use App\Models\CarEvent;
use App\Models\CarResponse;
use App\Models\Category;
use App\Models\Farm;
use App\Models\IssuedToUnit;
use App\Models\Subcategory;
use App\Models\User;
use App\Services\CarWorkflow;
use Carbon\CarbonImmutable;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    Carbon::setTestNow('2026-10-05 09:00:00');
    $this->workflow = app(CarWorkflow::class);
});

/**
 * A user with the given role who is allowed to act on the CAR's farm; the Requestor is the filer.
 */
function actorFor(Role $role, Car $car): User
{
    if ($role === Role::Requestor) {
        return $car->requestor;
    }

    return User::factory()->create([
        'role' => $role,
        'farm_id' => $role->isFarmScoped() ? $car->farm_id : null,
    ]);
}

/**
 * The expected permission matrix, written out independently of CarWorkflow::transitions().
 *
 * @return array<string, list<array{0: CarAction, 1: Role, 2: CarStatus}>>
 */
function expectedTransitions(): array
{
    return [
        CarStatus::AwaitingRelease->value => [
            [CarAction::Release, Role::RequestorApprover, CarStatus::AwaitingResponder],
            [CarAction::Reject, Role::RequestorApprover, CarStatus::ReturnedToRequestor],
        ],
        CarStatus::ReturnedToRequestor->value => [
            [CarAction::Resubmit, Role::Requestor, CarStatus::AwaitingRelease],
        ],
        CarStatus::AwaitingResponder->value => [
            [CarAction::SubmitResponse, Role::Responder, CarStatus::AwaitingResponderApproval],
            [CarAction::SubmitResponse, Role::ResponderApprover, CarStatus::AwaitingResponderApproval],
        ],
        CarStatus::ReturnedToResponder->value => [
            [CarAction::SubmitResponse, Role::Responder, CarStatus::AwaitingResponderApproval],
            [CarAction::SubmitResponse, Role::ResponderApprover, CarStatus::AwaitingResponderApproval],
        ],
        CarStatus::AwaitingResponderApproval->value => [
            [CarAction::ApproveResponse, Role::ResponderApprover, CarStatus::AwaitingImplementation],
            [CarAction::ReturnResponse, Role::ResponderApprover, CarStatus::ReturnedToResponder],
        ],
        CarStatus::AwaitingImplementation->value => [
            [CarAction::UploadEvidence, Role::Responder, CarStatus::AwaitingEffectivenessCheck],
        ],
        CarStatus::AwaitingEffectivenessCheck->value => [
            [CarAction::MarkEffective, Role::ResponderApprover, CarStatus::AwaitingRequestorApproval],
            [CarAction::MarkNotEffective, Role::ResponderApprover, CarStatus::ReturnedToResponder],
        ],
        CarStatus::AwaitingRequestorApproval->value => [
            [CarAction::Accept, Role::RequestorApprover, CarStatus::ClosedAccepted],
            [CarAction::NotAccept, Role::RequestorApprover, CarStatus::OpenNotAccepted],
        ],
        CarStatus::OpenNotAccepted->value => [
            [CarAction::UploadEvidence, Role::Responder, CarStatus::AwaitingEffectivenessCheck],
        ],
        CarStatus::ClosedAccepted->value => [],
        CarStatus::Voided->value => [],
    ];
}

/**
 * @return array{farm_id: int, issued_to_unit_id: int, category_id: int, subcategory_id: int, issued_by: string, complainant: string, complaint_type: ComplaintType, problem_details: string}
 */
function funaDetails(): array
{
    test()->seed(ReferenceDataSeeder::class);
    $category = Category::whereRelation('businessLine', 'name', 'TABLE EGG')->where('name', 'Production Related')->sole();

    return [
        'farm_id' => Farm::where('name', 'PFC')->value('id'),
        'issued_to_unit_id' => IssuedToUnit::where('name', 'PFC Production')->value('id'),
        'category_id' => $category->id,
        'subcategory_id' => Subcategory::where('category_id', $category->id)->where('name', 'Internal Quality Defects')->value('id'),
        'issued_by' => 'Gab Maglalang — Requestor',
        'complainant' => 'Rollie Funa',
        'complaint_type' => ComplaintType::Product,
        'problem_details' => '34 trays and 7 pcs of big dirty eggs returned as spoiled.',
    ];
}

describe('submitting a CAR', function () {
    it('issues a CAR awaiting release with a reference, snapshotted deadlines, round 1 and a history entry', function () {
        Event::fake([CarTransitioned::class]);
        $requestor = User::factory()->role(Role::Requestor)->create();

        $car = $this->workflow->submit($requestor, funaDetails());

        expect($car->fresh())
            ->reference->toBe('CAR-2026-0001')
            ->status->toBe(CarStatus::AwaitingRelease)
            ->requestor_id->toBe($requestor->id)
            ->issued_on->toDateString()->toBe('2026-10-05')
            ->response_days->toBe(3)
            ->implementation_days->toBe(10)
            ->response_due_on->toDateString()->toBe('2026-10-08')
            ->implementation_due_on->toDateString()->toBe('2026-10-15')
            ->current_round->toBe(1)
            ->and($car->rounds()->pluck('number')->all())->toBe([1])
            ->and(CarEvent::sole())
            ->action->toBe(CarAction::Submit)
            ->from_status->toBeNull()
            ->to_status->toBe(CarStatus::AwaitingRelease)
            ->actor_id->toBe($requestor->id);

        Event::assertDispatched(CarTransitioned::class, fn (CarTransitioned $event): bool => $event->action === CarAction::Submit && $event->car->is($car));
    });

    it('keeps its deadlines when the matrix changes afterwards', function () {
        $car = $this->workflow->submit(User::factory()->role(Role::Requestor)->create(), funaDetails());

        $car->category->update(['response_days' => 1, 'implementation_days' => 2]);

        expect($car->fresh())
            ->response_due_on->toDateString()->toBe('2026-10-08')
            ->implementation_due_on->toDateString()->toBe('2026-10-15');
    });

    it('numbers CARs one after another', function () {
        $requestor = User::factory()->role(Role::Requestor)->create();
        $details = funaDetails();

        $first = $this->workflow->submit($requestor, $details);
        $second = $this->workflow->submit($requestor, $details);

        expect([$first->reference, $second->reference])->toBe(['CAR-2026-0001', 'CAR-2026-0002']);
    });

    it('refuses anyone but an active requestor', function (Role $role) {
        $user = User::factory()->role($role)->create();

        expect(fn () => $this->workflow->submit($user, funaDetails()))->toThrow(AuthorizationException::class);
        expect(Car::count())->toBe(0);
    })->with([Role::RequestorApprover, Role::Responder, Role::ResponderApprover, Role::Monitor, Role::Admin]);

    it('refuses a deactivated requestor', function () {
        $requestor = User::factory()->role(Role::Requestor)->inactive()->create();

        expect(fn () => $this->workflow->submit($requestor, funaDetails()))->toThrow(AuthorizationException::class);
    });

    it('rejects a sub-category from another category', function () {
        $details = funaDetails();
        $details['subcategory_id'] = Subcategory::whereRelation('category', 'name', 'Sales & Order Management')->value('id');

        expect(fn () => $this->workflow->submit(User::factory()->role(Role::Requestor)->create(), $details))
            ->toThrow(ValidationException::class, 'The sub-category does not belong to the selected category.');
        expect(Car::count())->toBe(0);
    });

    it('rejects a category from the other business line', function () {
        $details = funaDetails();
        $dopCategory = Category::whereRelation('businessLine', 'name', 'DOP')->where('name', 'Sales & Order Management')->sole();
        $details['category_id'] = $dopCategory->id;
        $details['subcategory_id'] = $dopCategory->subcategories()->value('id');

        expect(fn () => $this->workflow->submit(User::factory()->role(Role::Requestor)->create(), $details))
            ->toThrow(ValidationException::class, "The category does not belong to the selected unit's business line.");
    });
});

describe('resubmitting a returned CAR', function () {
    it('saves the corrections, recalculates deadlines from the issued date and goes back for release', function () {
        $details = funaDetails();
        $requestor = User::factory()->role(Role::Requestor)->create();
        $car = $this->workflow->submit($requestor, $details);
        $this->workflow->apply($car, User::factory()->role(Role::RequestorApprover)->create(), CarAction::Reject, note: 'Wrong category.');

        Carbon::setTestNow('2026-10-07 09:00:00');
        $compliance = Category::whereRelation('businessLine', 'name', 'TABLE EGG')->where('name', 'Compliance & Standards')->sole();
        $this->workflow->resubmit($car, $requestor, [
            ...$details,
            'category_id' => $compliance->id,
            'subcategory_id' => $compliance->subcategories()->where('name', 'Storage & Handling')->value('id'),
        ]);

        expect($car->fresh())
            ->status->toBe(CarStatus::AwaitingRelease)
            ->category_id->toBe($compliance->id)
            ->issued_on->toDateString()->toBe('2026-10-05')
            ->response_due_on->toDateString()->toBe('2026-10-06')
            ->implementation_due_on->toDateString()->toBe('2026-10-10')
            ->and($car->events()->pluck('action')->all())->toBe([CarAction::Submit, CarAction::Reject, CarAction::Resubmit]);
    });

    it('refuses anyone but the filing requestor and leaves the CAR untouched', function () {
        $car = Car::factory()->status(CarStatus::ReturnedToRequestor)->create(['complainant' => 'Original']);

        expect(fn () => $this->workflow->resubmit($car, User::factory()->role(Role::Requestor)->create(), [...funaDetails(), 'complainant' => 'Changed']))
            ->toThrow(AuthorizationException::class);
        expect($car->fresh())
            ->complainant->toBe('Original')
            ->status->toBe(CarStatus::ReturnedToRequestor);
    });
});

describe('the transition table', function () {
    it('allows exactly the expected role and action in every status', function () {
        foreach (CarStatus::cases() as $status) {
            $car = Car::factory()->status($status)->create();
            $allowed = collect(expectedTransitions()[$status->value])
                ->map(fn (array $row): string => "{$row[0]->value}:{$row[1]->value}")
                ->push(...($status->isOpen() ? [CarAction::Void->value.':'.Role::Admin->value] : []));

            foreach (Role::cases() as $role) {
                $actor = actorFor($role, $car);

                foreach (CarAction::cases() as $action) {
                    expect($this->workflow->can($car, $actor, $action))
                        ->toBe($allowed->contains("{$action->value}:{$role->value}"), "{$status->label()} · {$role->label()} · {$action->label()}");
                }
            }
        }
    });

    it('moves the CAR to the next status and records who did it', function (CarStatus $from, CarAction $action, Role $role, CarStatus $to) {
        Event::fake([CarTransitioned::class]);
        $car = Car::factory()->status($from)
            ->when($action === CarAction::SubmitResponse, fn ($factory) => $factory->withResponse())
            ->when($action === CarAction::UploadEvidence, fn ($factory) => $factory->withEvidence())
            ->create();
        $actor = actorFor($role, $car);

        $this->workflow->apply($car, $actor, $action, note: 'Reason given', newDueOn: CarbonImmutable::parse('2026-10-20'));

        expect($car->status)->toBe($to)
            ->and($car->fresh()->status)->toBe($to)
            ->and($car->events()->sole())
            ->action->toBe($action)
            ->from_status->toBe($from)
            ->to_status->toBe($to)
            ->actor_id->toBe($actor->id);

        Event::assertDispatched(CarTransitioned::class);
    })->with(function () {
        foreach (expectedTransitions() as $from => $rows) {
            foreach ($rows as [$action, $role, $to]) {
                yield "{$from} → {$action->value}" => [CarStatus::from($from), $action, $role, $to];
            }
        }
    });

    it('refuses a forbidden action without changing anything', function () {
        $car = Car::factory()->status(CarStatus::AwaitingRelease)->create();

        expect(fn () => $this->workflow->apply($car, actorFor(Role::Responder, $car), CarAction::Release))
            ->toThrow(AuthorizationException::class, 'A Responder cannot "Approve & release"');

        expect($car->fresh()->status)->toBe(CarStatus::AwaitingRelease)
            ->and(CarEvent::count())->toBe(0);
    });

    it('re-reads the status under lock, so a stale copy cannot replay a transition', function () {
        $car = Car::factory()->status(CarStatus::AwaitingRelease)->create();
        $staleCopy = Car::find($car->id);
        $approver = actorFor(Role::RequestorApprover, $car);

        $this->workflow->apply($car, $approver, CarAction::Release);

        expect(fn () => $this->workflow->apply($staleCopy, $approver, CarAction::Release))->toThrow(AuthorizationException::class);
        expect(CarEvent::count())->toBe(1);
    });

    it('does not create CARs through apply', function () {
        $car = Car::factory()->create();

        expect(fn () => $this->workflow->apply($car, $car->requestor, CarAction::Submit))->toThrow(InvalidArgumentException::class);
    });
});

describe('who may act', function () {
    it('limits responder roles to their own farm', function (Role $role, CarStatus $status, CarAction $action) {
        $car = Car::factory()->forFarm('PFC')->status($status)->create();
        $otherFarmUser = User::factory()->role($role, 'HATCHERY')->create();

        expect($this->workflow->can($car, $otherFarmUser, $action))->toBeFalse()
            ->and($this->workflow->can($car, actorFor($role, $car), $action))->toBeTrue();
    })->with([
        [Role::Responder, CarStatus::AwaitingResponder, CarAction::SubmitResponse],
        [Role::ResponderApprover, CarStatus::AwaitingResponderApproval, CarAction::ApproveResponse],
    ]);

    it('lets only the requestor who filed the CAR resubmit it', function () {
        $car = Car::factory()->status(CarStatus::ReturnedToRequestor)->create();

        expect($this->workflow->can($car, User::factory()->role(Role::Requestor)->create(), CarAction::Resubmit))->toBeFalse()
            ->and($this->workflow->can($car, $car->requestor, CarAction::Resubmit))->toBeTrue();
    });

    it('lets deactivated users do nothing', function () {
        $car = Car::factory()->status(CarStatus::AwaitingRelease)->create();
        $approver = User::factory()->role(Role::RequestorApprover)->inactive()->create();

        expect($this->workflow->availableActions($car, $approver))->toBe([]);
    });

    it('lists the actions available to a user in table order', function () {
        $car = Car::factory()->status(CarStatus::AwaitingRelease)->create();

        expect($this->workflow->availableActions($car, actorFor(Role::RequestorApprover, $car)))
            ->toBe([CarAction::Release, CarAction::Reject]);
    });
});

describe('Phase II responses', function () {
    it('will not submit a response that has not been started', function () {
        $car = Car::factory()->status(CarStatus::AwaitingResponder)->create();

        expect(fn () => $this->workflow->apply($car, actorFor(Role::Responder, $car), CarAction::SubmitResponse))
            ->toThrow(ValidationException::class, 'Complete the interim containment, the root cause and at least one corrective action before submitting.');
        expect($car->fresh()->status)->toBe(CarStatus::AwaitingResponder);
    });

    it('will not submit an incomplete draft', function () {
        $car = Car::factory()->status(CarStatus::AwaitingResponder)->create();
        CarResponse::factory()->draft()->create(['car_id' => $car->id]);

        expect(fn () => $this->workflow->apply($car, actorFor(Role::Responder, $car), CarAction::SubmitResponse))
            ->toThrow(ValidationException::class);
    });

    it('records who submitted the response and when', function () {
        $car = Car::factory()->status(CarStatus::AwaitingResponder)->withResponse()->create();
        $responder = actorFor(Role::Responder, $car);

        $this->workflow->apply($car, $responder, CarAction::SubmitResponse);

        expect($car->currentResponse())
            ->prepared_by->toBe($responder->id)
            ->submitted_at->toDateTimeString()->toBe('2026-10-05 09:00:00');
    });

    it('never lets someone review a response they prepared', function () {
        $approver = User::factory()->role(Role::ResponderApprover)->create();
        $car = Car::factory()->forFarm($approver->farm->name)->status(CarStatus::AwaitingResponderApproval)->withResponse($approver, submitted: true)->create();

        expect($this->workflow->can($car, $approver, CarAction::ApproveResponse))->toBeFalse()
            ->and($this->workflow->can($car, $approver, CarAction::ReturnResponse))->toBeFalse()
            ->and($this->workflow->can($car, actorFor(Role::ResponderApprover, $car), CarAction::ApproveResponse))->toBeTrue();
    });

    it('sends a response prepared by a Responder Approver to that person\'s own approver', function () {
        $senior = User::factory()->role(Role::ResponderApprover)->create();
        $preparer = User::factory()->role(Role::ResponderApprover)->create(['farm_id' => $senior->farm_id, 'approver_id' => $senior->id]);
        $car = Car::factory()->forFarm($senior->farm->name)->status(CarStatus::AwaitingResponderApproval)->withResponse($preparer, submitted: true)->create();

        expect($this->workflow->can($car, $senior, CarAction::ApproveResponse))->toBeTrue()
            ->and($this->workflow->can($car, actorFor(Role::ResponderApprover, $car), CarAction::ApproveResponse))->toBeFalse();
    });

    it('lets any Responder Approver on the farm review a Responder\'s response', function () {
        $responder = User::factory()->role(Role::Responder)->create();
        $car = Car::factory()->forFarm($responder->farm->name)->status(CarStatus::AwaitingResponderApproval)->withResponse($responder, submitted: true)->create();

        expect($this->workflow->can($car, actorFor(Role::ResponderApprover, $car), CarAction::ApproveResponse))->toBeTrue();
    });
});

describe('Phase III evidence', function () {
    it('will not accept "upload evidence" without a file on the round', function () {
        $car = Car::factory()->status(CarStatus::AwaitingImplementation)->create();

        expect(fn () => $this->workflow->apply($car, actorFor(Role::Responder, $car), CarAction::UploadEvidence))
            ->toThrow(ValidationException::class, 'Attach at least one file or photo proving the corrective actions were carried out.');
        expect($car->fresh()->status)->toBe(CarStatus::AwaitingImplementation);
    });

    it('records who uploaded the evidence and when', function () {
        $car = Car::factory()->status(CarStatus::AwaitingImplementation)->withEvidence()->create();
        $responder = actorFor(Role::Responder, $car);

        $this->workflow->apply($car, $responder, CarAction::UploadEvidence);

        expect($car->currentRound()->first())
            ->evidence_uploaded_by->toBe($responder->id)
            ->evidence_uploaded_at->toDateTimeString()->toBe('2026-10-05 09:00:00');
    });

    it('needs fresh evidence in the new round after "not accepted"', function () {
        $car = Car::factory()->status(CarStatus::AwaitingRequestorApproval)->withEvidence()->create();
        $this->workflow->apply($car, actorFor(Role::RequestorApprover, $car), CarAction::NotAccept, newDueOn: CarbonImmutable::parse('2026-10-20'));

        expect(fn () => $this->workflow->apply($car->fresh(), actorFor(Role::Responder, $car), CarAction::UploadEvidence))
            ->toThrow(ValidationException::class);
    });
});

describe('side effects', function () {
    it('stamps the release time', function () {
        $car = Car::factory()->status(CarStatus::AwaitingRelease)->create();

        $this->workflow->apply($car, actorFor(Role::RequestorApprover, $car), CarAction::Release);

        expect($car->fresh()->released_at->toDateTimeString())->toBe('2026-10-05 09:00:00');
    });

    it('stamps the close time on acceptance', function () {
        $car = Car::factory()->status(CarStatus::AwaitingRequestorApproval)->create();

        $this->workflow->apply($car, actorFor(Role::RequestorApprover, $car), CarAction::Accept);

        expect($car->fresh()->closed_at->toDateTimeString())->toBe('2026-10-05 09:00:00');
    });

    it('opens a new round with the revised end date when not accepted', function () {
        $car = Car::factory()->status(CarStatus::AwaitingRequestorApproval)->create();

        $this->workflow->apply($car, actorFor(Role::RequestorApprover, $car), CarAction::NotAccept, newDueOn: CarbonImmutable::parse('2026-10-20'));

        $car->refresh();
        expect($car)
            ->current_round->toBe(2)
            ->revised_due_on->toDateString()->toBe('2026-10-20')
            ->and($car->activeDueOn()->toDateString())->toBe('2026-10-20')
            ->and($car->currentRound)
            ->number->toBe(2)
            ->opened_by_action->toBe(CarAction::NotAccept)
            ->due_on->toDateString()->toBe('2026-10-20')
            ->and($car->events()->sole()->round)->toBe(1);
    });

    it('opens a new round when the corrective action is not effective', function () {
        $car = Car::factory()->status(CarStatus::AwaitingEffectivenessCheck)->create();

        $this->workflow->apply($car, actorFor(Role::ResponderApprover, $car), CarAction::MarkNotEffective, note: 'Cracks still found at QA.');

        expect($car->fresh())
            ->current_round->toBe(2)
            ->and($car->rounds()->pluck('number')->all())->toBe([1, 2]);
    });

    it('keeps the round when a response is returned for revision', function () {
        $car = Car::factory()->status(CarStatus::AwaitingResponderApproval)->create();

        $this->workflow->apply($car, actorFor(Role::ResponderApprover, $car), CarAction::ReturnResponse, note: 'Root cause is too vague.');

        expect($car->fresh()->current_round)->toBe(1)
            ->and($car->events()->sole()->note)->toBe('Root cause is too vague.');
    });

    it('lets the IT Admin void an open CAR with a reason', function () {
        $car = Car::factory()->status(CarStatus::AwaitingResponder)->create();

        $this->workflow->apply($car, actorFor(Role::Admin, $car), CarAction::Void, note: 'Duplicate of CAR-2026-0001.');

        expect($car->fresh())
            ->status->toBe(CarStatus::Voided)
            ->voided_at->not->toBeNull()
            ->and($car->events()->sole()->note)->toBe('Duplicate of CAR-2026-0001.');
    });
});

describe('required input', function () {
    it('requires a reason to reject, return, mark not effective or void', function (CarStatus $status, CarAction $action, Role $role) {
        $car = Car::factory()->status($status)->create();

        expect(fn () => $this->workflow->apply($car, actorFor($role, $car), $action, note: '   '))
            ->toThrow(ValidationException::class, "Give a reason for \"{$action->label()}\".");
        expect($car->fresh()->status)->toBe($status);
    })->with([
        [CarStatus::AwaitingRelease, CarAction::Reject, Role::RequestorApprover],
        [CarStatus::AwaitingResponderApproval, CarAction::ReturnResponse, Role::ResponderApprover],
        [CarStatus::AwaitingEffectivenessCheck, CarAction::MarkNotEffective, Role::ResponderApprover],
        [CarStatus::AwaitingResponder, CarAction::Void, Role::Admin],
    ]);

    it('requires a new end date after today when not accepting', function (?string $newDueOn) {
        $car = Car::factory()->status(CarStatus::AwaitingRequestorApproval)->create();

        expect(fn () => $this->workflow->apply($car, actorFor(Role::RequestorApprover, $car), CarAction::NotAccept, newDueOn: $newDueOn ? CarbonImmutable::parse($newDueOn) : null))
            ->toThrow(ValidationException::class, 'The new end date must be after today.');
        expect($car->fresh()->status)->toBe(CarStatus::AwaitingRequestorApproval);
    })->with(['missing' => [null], 'today' => ['2026-10-05'], 'past' => ['2026-10-01']]);
});
