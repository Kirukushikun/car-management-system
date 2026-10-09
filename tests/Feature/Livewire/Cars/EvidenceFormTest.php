<?php

use App\Enums\CarAction;
use App\Enums\CarStatus;
use App\Enums\Role;
use App\Livewire\Cars\EvidenceForm;
use App\Models\Attachment;
use App\Models\Car;
use App\Models\User;
use App\Services\CarWorkflow;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    Storage::fake('local');
    $this->car = Car::factory()->forFarm('PFC')->status(CarStatus::AwaitingImplementation)->create();
    $this->responder = User::factory()->role(Role::Responder, 'PFC')->create(['name' => 'Roi Andre D. Capiz']);
});

it('uploads the evidence to the round and sends the CAR to the effectiveness check', function () {
    Livewire::actingAs($this->responder)
        ->test(EvidenceForm::class, ['car' => $this->car])
        ->assertSet('responsible', 'Roi Andre D. Capiz')
        ->set('files', [UploadedFile::fake()->image('cold-room.jpg'), UploadedFile::fake()->create('checklist.pdf', 50, 'application/pdf')])
        ->set('notes', 'Cold room in use since Oct 6.')
        ->call('submit')
        ->assertHasNoErrors()
        ->assertRedirect(route('cars.show', $this->car));

    $round = $this->car->fresh()->currentRound;

    expect($this->car->fresh()->status)->toBe(CarStatus::AwaitingEffectivenessCheck)
        ->and($round)
        ->evidence_responsible->toBe('Roi Andre D. Capiz')
        ->evidence_notes->toBe('Cold room in use since Oct 6.')
        ->evidence_uploaded_by->toBe($this->responder->id)
        ->and($round->attachments->pluck('original_name')->all())->toBe(['cold-room.jpg', 'checklist.pdf'])
        ->and($round->attachments->pluck('collection')->unique()->all())->toBe([Attachment::IMPLEMENTATION_EVIDENCE]);
    $round->attachments->each(fn ($file) => Storage::disk('local')->assertExists($file->path));
});

it('requires at least one file and a responsible person', function () {
    Livewire::actingAs($this->responder)
        ->test(EvidenceForm::class, ['car' => $this->car])
        ->set('responsible', '')
        ->call('submit')
        ->assertHasErrors(['files' => 'required', 'responsible' => 'required'])
        ->assertSee('Attach at least one file or photo proving the corrective actions were carried out.');

    expect($this->car->fresh()->status)->toBe(CarStatus::AwaitingImplementation);
});

it('rejects file types that are not evidence', function () {
    Livewire::actingAs($this->responder)
        ->test(EvidenceForm::class, ['car' => $this->car])
        ->set('files', [UploadedFile::fake()->create('run.bat', 1)])
        ->call('submit')
        ->assertHasErrors('files.0');
});

it('is forbidden to anyone who may not upload evidence', function (Role $role, string $farm) {
    Livewire::actingAs(User::factory()->role($role, $farm)->create())
        ->test(EvidenceForm::class, ['car' => $this->car])
        ->assertForbidden();
})->with([
    'responder approver' => [Role::ResponderApprover, 'PFC'],
    'responder from another farm' => [Role::Responder, 'HATCHERY'],
]);

it('keeps the first round\'s evidence when new evidence is uploaded after "not accepted"', function () {
    $car = Car::factory()->forFarm('PFC')->status(CarStatus::AwaitingRequestorApproval)->withEvidence()->create();
    app(CarWorkflow::class)->apply($car, User::factory()->role(Role::RequestorApprover)->create(), CarAction::NotAccept, note: 'Still cracked.', newDueOn: CarbonImmutable::tomorrow()->addWeek());
    $car->fresh()->update(['status' => CarStatus::AwaitingImplementation]);

    Livewire::actingAs($this->responder)
        ->test(EvidenceForm::class, ['car' => $car->fresh()])
        ->assertSee('solution 2')
        ->set('files', [UploadedFile::fake()->image('re-implemented.jpg')])
        ->call('submit')
        ->assertHasNoErrors();

    $rounds = $car->fresh()->rounds()->with('attachments')->get();

    expect($car->fresh()->status)->toBe(CarStatus::AwaitingEffectivenessCheck)
        ->and($rounds->map(fn ($round) => $round->attachments->pluck('original_name')->all())->all())
        ->toBe([['evidence.jpg'], ['re-implemented.jpg']]);
});
