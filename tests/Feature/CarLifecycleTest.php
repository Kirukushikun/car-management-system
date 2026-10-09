<?php

use App\Enums\CarAction;
use App\Enums\CarStatus;
use App\Enums\Role;
use App\Livewire\Cars\Create;
use App\Livewire\Cars\EvidenceForm;
use App\Livewire\Cars\ResponseForm;
use App\Livewire\Cars\Show;
use App\Models\Car;
use App\Models\CarEvent;
use App\Models\Category;
use App\Models\IssuedToUnit;
use App\Models\Subcategory;
use App\Models\User;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

/**
 * The whole CAR process through the real screens, as UAT will walk it: the Rollie Funa complaint
 * from filing to closure, through both loops (not effective, not accepted).
 */
it('carries a CAR from filing to closure through every step and both loops', function () {
    Storage::fake('local');
    Carbon::setTestNow('2026-10-06 08:00:00');
    $this->seed(ReferenceDataSeeder::class);

    $gab = User::factory()->role(Role::Requestor)->create(['name' => 'Gab Maglalang']);
    $stephanie = User::factory()->role(Role::RequestorApprover)->create(['name' => 'Stephanie Flores']);
    $roi = User::factory()->role(Role::Responder, 'PFC')->create(['name' => 'Roi Andre D. Capiz']);
    $reneliza = User::factory()->role(Role::ResponderApprover, 'PFC')->create(['name' => 'Reneliza M. Yusi']);
    $category = Category::whereRelation('businessLine', 'name', 'TABLE EGG')->where('name', 'Production Related')->sole();

    // Phase I — file, release
    Livewire::actingAs($gab)->test(Create::class)
        ->set('unitId', IssuedToUnit::where('name', 'PFC Production')->value('id'))
        ->set('categoryId', $category->id)
        ->set('subcategoryId', Subcategory::where('category_id', $category->id)->where('name', 'Internal Quality Defects')->value('id'))
        ->set('complainant', 'Rollie Funa')
        ->set('problem', '34 trays and 7 pcs of big dirty eggs returned by his customer as spoiled.')
        ->set('attachments', [UploadedFile::fake()->create('customer-video.mp4', 900, 'video/mp4')])
        ->call('submit')->assertHasNoErrors();

    $car = Car::sole();
    expect($car->status)->toBe(CarStatus::AwaitingRelease);

    Livewire::actingAs($stephanie)->test(Show::class, ['car' => $car])->call('act', CarAction::Release->value);
    expect($car->fresh()->status)->toBe(CarStatus::AwaitingResponder);

    // Phase II — respond, approve
    Carbon::setTestNow('2026-10-07 09:00:00');
    Livewire::actingAs($roi)->test(ResponseForm::class, ['car' => $car->fresh()])
        ->set('containmentActions', 'Replaced the spoiled trays and held the remaining big dirty eggs.')
        ->set('containmentEndsOn', '2026-10-07')
        ->set('rootCause', 'Dirty eggs were dispatched three days after collection without cold storage.')
        ->set('correctiveActions.0.description', 'Move dirty eggs to cold storage on the day of collection.')
        ->call('submit')->assertHasNoErrors();

    Livewire::actingAs($reneliza)->test(Show::class, ['car' => $car->fresh()])->call('act', CarAction::ApproveResponse->value);
    expect($car->fresh()->status)->toBe(CarStatus::AwaitingImplementation);

    // Phase III — evidence, NOT effective → back to Phase II (round 2)
    Livewire::actingAs($roi)->test(EvidenceForm::class, ['car' => $car->fresh()])
        ->set('files', [UploadedFile::fake()->image('cold-room.jpg')])
        ->call('submit')->assertHasNoErrors();

    Livewire::actingAs($reneliza)->test(Show::class, ['car' => $car->fresh()])
        ->set('note', 'Customer reported spoilage again on Oct 8.')
        ->call('act', CarAction::MarkNotEffective->value);
    expect($car->fresh())->status->toBe(CarStatus::ReturnedToResponder)->current_round->toBe(2);

    Livewire::actingAs($roi)->test(ResponseForm::class, ['car' => $car->fresh()])
        ->assertSet('rootCause', 'Dirty eggs were dispatched three days after collection without cold storage.')
        ->set('rootCause', 'Cold room door seal was failing, so eggs warmed overnight.')
        ->call('submit')->assertHasNoErrors();
    Livewire::actingAs($reneliza)->test(Show::class, ['car' => $car->fresh()])->call('act', CarAction::ApproveResponse->value);
    Livewire::actingAs($roi)->test(EvidenceForm::class, ['car' => $car->fresh()])
        ->set('files', [UploadedFile::fake()->image('new-seal.jpg')])
        ->call('submit')->assertHasNoErrors();
    Livewire::actingAs($reneliza)->test(Show::class, ['car' => $car->fresh()])->call('act', CarAction::MarkEffective->value);
    expect($car->fresh()->status)->toBe(CarStatus::AwaitingRequestorApproval);

    // Step 13 — NOT accepted with a reason and a new end date → a new solution (round 3) → accepted with remarks
    Livewire::actingAs($stephanie)->test(Show::class, ['car' => $car->fresh()])
        ->set('newDueOn', '2026-10-20')
        ->set('note', 'Spoilage reports continued from the Lucena outlet.')
        ->call('act', CarAction::NotAccept->value)->assertHasNoErrors();
    expect($car->fresh())->status->toBe(CarStatus::ReturnedToResponder)->current_round->toBe(3);

    Livewire::actingAs($roi)->test(Show::class, ['car' => $car->fresh()])
        ->assertSee(['Propose solution 3', 'Spoilage reports continued from the Lucena outlet.']);
    Livewire::actingAs($roi)->test(ResponseForm::class, ['car' => $car->fresh()])
        ->set('rootCause', 'Outlet stores eggs next to the oven; rotate stock with FIFO racks.')
        ->call('submit')->assertHasNoErrors();
    Livewire::actingAs($reneliza)->test(Show::class, ['car' => $car->fresh()])->call('act', CarAction::ApproveResponse->value);
    Livewire::actingAs($roi)->test(EvidenceForm::class, ['car' => $car->fresh()])
        ->set('files', [UploadedFile::fake()->image('fifo-rack.jpg')])
        ->call('submit')->assertHasNoErrors();
    Livewire::actingAs($reneliza)->test(Show::class, ['car' => $car->fresh()])->call('act', CarAction::MarkEffective->value);
    Livewire::actingAs($stephanie)->test(Show::class, ['car' => $car->fresh()])
        ->set('note', 'No complaints for two weeks.')
        ->call('act', CarAction::Accept->value);

    $car->refresh();
    expect($car->status)->toBe(CarStatus::ClosedAccepted)
        ->and($car->closed_at)->not->toBeNull()
        ->and($car->responses()->count())->toBe(3)
        ->and($car->rounds()->count())->toBe(3)
        ->and(CarEvent::where('car_id', $car->id)->pluck('action')->map->value->all())->toBe([
            'submit', 'release', 'submit_response', 'approve_response', 'upload_evidence', 'mark_not_effective',
            'submit_response', 'approve_response', 'upload_evidence', 'mark_effective', 'not_accept',
            'submit_response', 'approve_response', 'upload_evidence', 'mark_effective', 'accept',
        ])
        ->and($car->events()->where('action', CarAction::Accept)->value('note'))->toBe('No complaints for two weeks.')
        ->and($gab->notifications()->where('data->message', 'Your CAR was accepted and closed')->exists())->toBeTrue();
});
