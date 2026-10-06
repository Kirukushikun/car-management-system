<?php

use App\Enums\CarAction;
use App\Enums\CarStatus;
use App\Enums\Role;
use App\Livewire\Cars\ResponseForm;
use App\Models\Attachment;
use App\Models\Car;
use App\Models\CarResponse;
use App\Models\User;
use App\Services\CarWorkflow;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

beforeEach(function () {
    Carbon::setTestNow('2026-10-05 09:00:00');
    $this->car = Car::factory()->forFarm('PFC')->status(CarStatus::AwaitingResponder)->create([
        'issued_on' => '2026-10-03',
        'implementation_due_on' => '2026-10-13',
    ]);
    $this->responder = User::factory()->role(Role::Responder, 'PFC')->create(['name' => 'Roi Andre D. Capiz']);
});

function completedResponseForm(Car $car, User $responder): Testable
{
    return Livewire::actingAs($responder)
        ->test(ResponseForm::class, ['car' => $car])
        ->set('containmentActions', 'Replaced the 34 trays and held the remaining big dirty eggs.')
        ->set('containmentStartsOn', '2026-10-04')
        ->set('containmentEndsOn', '2026-10-05')
        ->set('rootCause', 'Dirty eggs sat three days at room temperature before dispatch.')
        ->set('correctiveActions.0.description', 'Move dirty eggs to cold storage on the day of collection.');
}

describe('opening the form', function () {
    it('starts with the responder as the responsible person and one corrective action due by the deadline', function () {
        Livewire::actingAs($this->responder)
            ->test(ResponseForm::class, ['car' => $this->car])
            ->assertSet('containmentResponsible', 'Roi Andre D. Capiz')
            ->assertSet('rootCauseResponsible', 'Roi Andre D. Capiz')
            ->assertSet('containmentStartsOn', '2026-10-05')
            ->assertCount('correctiveActions', 1)
            ->assertSet('correctiveActions.0.ends_on', '2026-10-13')
            ->assertSee('must finish by the implementation deadline — Oct 13, 2026');
    });

    it('starts blank corrective actions when containment ends', function () {
        Livewire::actingAs($this->responder)
            ->test(ResponseForm::class, ['car' => $this->car])
            ->set('containmentEndsOn', '2026-10-06')
            ->assertSet('correctiveActions.0.starts_on', '2026-10-06')
            ->call('addAction')
            ->assertSet('correctiveActions.1.starts_on', '2026-10-06')
            ->assertSet('correctiveActions.1.ends_on', '2026-10-13');
    });

    it('is forbidden to anyone who may not submit the response', function (Role $role, string $farm) {
        $user = User::factory()->role($role, $farm)->create();

        Livewire::actingAs($user)->test(ResponseForm::class, ['car' => $this->car])->assertForbidden();
    })->with([
        'requestor approver' => [Role::RequestorApprover, 'PFC'],
        'responder from another farm' => [Role::Responder, 'HATCHERY'],
        'monitor' => [Role::Monitor, 'PFC'],
    ]);
});

describe('saving a draft', function () {
    it('keeps partial work without moving the CAR', function () {
        Livewire::actingAs($this->responder)
            ->test(ResponseForm::class, ['car' => $this->car])
            ->set('containmentActions', 'Called the customer.')
            ->call('saveDraft')
            ->assertHasNoErrors()
            ->assertSee('Draft saved.');

        expect($this->car->fresh()->status)->toBe(CarStatus::AwaitingResponder)
            ->and($this->car->fresh()->currentResponse())
            ->containment_actions->toBe('Called the customer.')
            ->submitted_at->toBeNull();
    });

    it('reloads the draft next time the form opens', function () {
        CarResponse::factory()->create(['car_id' => $this->car->id, 'containment_actions' => 'Saved earlier.']);

        Livewire::actingAs($this->responder)
            ->test(ResponseForm::class, ['car' => $this->car])
            ->assertSet('containmentActions', 'Saved earlier.');
    });
});

describe('submitting for approval', function () {
    it('saves the response and sends the CAR to the Responder Approver', function () {
        completedResponseForm($this->car, $this->responder)
            ->call('addAction')
            ->set('correctiveActions.1.description', 'Dispatch first-in, first-out from the dirty-egg rack.')
            ->call('submit')
            ->assertHasNoErrors()
            ->assertRedirect(route('cars.show', $this->car));

        $response = $this->car->fresh()->currentResponse();

        expect($this->car->fresh()->status)->toBe(CarStatus::AwaitingResponderApproval)
            ->and($response)
            ->prepared_by->toBe($this->responder->id)
            ->submitted_at->not->toBeNull()
            ->root_cause->toBe('Dirty eggs sat three days at room temperature before dispatch.')
            ->and($response->correctiveActions->pluck('description')->all())->toBe([
                'Move dirty eggs to cold storage on the day of collection.',
                'Dispatch first-in, first-out from the dirty-egg rack.',
            ])
            ->and($response->correctiveActions->first()->starts_on->toDateString())->toBe('2026-10-05');
    });

    it('requires every Step 7–9 field', function () {
        Livewire::actingAs($this->responder)
            ->test(ResponseForm::class, ['car' => $this->car])
            ->set('containmentResponsible', '')
            ->call('submit')
            ->assertHasErrors([
                'containmentActions' => 'required',
                'containmentEndsOn' => 'required',
                'containmentResponsible' => 'required',
                'rootCause' => 'required',
                'correctiveActions.0.description' => 'required',
            ]);

        expect($this->car->fresh()->status)->toBe(CarStatus::AwaitingResponder);
    });

    it('accepts an attached file instead of a typed root cause', function () {
        Storage::fake('local');

        completedResponseForm($this->car, $this->responder)
            ->set('rootCause', '')
            ->set('rootCauseFiles', [UploadedFile::fake()->create('dispatch-form.pdf', 200, 'application/pdf')])
            ->call('submit')
            ->assertHasNoErrors();

        $file = $this->car->fresh()->currentResponse()->attachments->sole();
        expect($file)
            ->collection->toBe(Attachment::ROOT_CAUSE)
            ->original_name->toBe('dispatch-form.pdf');
        Storage::disk('local')->assertExists($file->path);
    });

    it('rejects a corrective action that ends after the implementation deadline', function () {
        completedResponseForm($this->car, $this->responder)
            ->set('correctiveActions.0.ends_on', '2026-10-14')
            ->call('submit')
            ->assertHasErrors('correctiveActions.0.ends_on')
            ->assertSee('Corrective actions must end by the implementation deadline (Oct 13, 2026).');
    });

    it('rejects dates that end before they start', function () {
        completedResponseForm($this->car, $this->responder)
            ->set('containmentEndsOn', '2026-10-03')
            ->set('correctiveActions.0.starts_on', '2026-10-08')
            ->set('correctiveActions.0.ends_on', '2026-10-07')
            ->call('submit')
            ->assertHasErrors(['containmentEndsOn', 'correctiveActions.0.ends_on'])
            ->assertSee(['Containment cannot end before it starts.', 'An action cannot end before it starts.']);
    });

    it('requires at least one corrective action', function () {
        completedResponseForm($this->car, $this->responder)
            ->call('removeAction', 0)
            ->call('submit')
            ->assertHasErrors('correctiveActions');
    });

    it('lets a Responder Approver prepare the response', function () {
        $approver = User::factory()->role(Role::ResponderApprover, 'PFC')->create();

        completedResponseForm($this->car, $approver)
            ->call('submit')
            ->assertHasNoErrors();

        expect($this->car->fresh()->currentResponse()->prepared_by)->toBe($approver->id);
    });
});

describe('later rounds', function () {
    it('reopens a returned response for revision', function () {
        $car = Car::factory()->forFarm('PFC')->status(CarStatus::ReturnedToResponder)->create();
        CarResponse::factory()->create(['car_id' => $car->id, 'root_cause' => 'First attempt.', 'submitted_at' => now()]);

        Livewire::actingAs($this->responder)
            ->test(ResponseForm::class, ['car' => $car])
            ->assertSet('rootCause', 'First attempt.')
            ->assertSee('Revise the response');
    });

    it('starts a new round from the previous round\'s answer', function () {
        $car = Car::factory()->forFarm('PFC')->status(CarStatus::AwaitingEffectivenessCheck)->withResponse(submitted: true)->create();
        app(CarWorkflow::class)->apply($car, User::factory()->role(Role::ResponderApprover, 'PFC')->create(), CarAction::MarkNotEffective, note: 'Still spoiling.');

        Livewire::actingAs($this->responder)
            ->test(ResponseForm::class, ['car' => $car->fresh()])
            ->assertSet('rootCause', 'Eggs were dispatched three days after collection without cold storage.')
            ->assertSee('round 2')
            ->set('rootCause', 'Cold room door seal was also failing.')
            ->call('saveDraft');

        expect($car->fresh()->responses()->count())->toBe(2)
            ->and($car->fresh()->responses()->first()->root_cause)->toBe('Eggs were dispatched three days after collection without cold storage.');
    });
});
