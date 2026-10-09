<?php

use App\Enums\CarStatus;
use App\Enums\ComplaintType;
use App\Enums\Role;
use App\Livewire\Cars\Create;
use App\Models\Attachment;
use App\Models\Car;
use App\Models\Category;
use App\Models\IssuedToUnit;
use App\Models\Subcategory;
use App\Models\User;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

beforeEach(function () {
    Carbon::setTestNow('2026-10-02 10:00:00');
    $this->seed(ReferenceDataSeeder::class);
    $this->requestor = User::factory()->role(Role::Requestor)->create(['name' => 'Gab Maglalang']);
});

function unitId(string $name): int
{
    return IssuedToUnit::where('name', $name)->value('id');
}

function categoryId(string $line, string $name): int
{
    return Category::whereRelation('businessLine', 'name', $line)->where('name', $name)->value('id');
}

function subcategoryId(string $line, string $category, string $name): int
{
    return Subcategory::where('category_id', categoryId($line, $category))->where('name', $name)->value('id');
}

function filledCreateForm(User $requestor): Testable
{
    return Livewire::actingAs($requestor)
        ->test(Create::class)
        ->set('unitId', unitId('PFC Production'))
        ->set('subcategoryId', subcategoryId('TABLE EGG', 'Production Related', 'Internal Quality Defects'))
        ->set('complainant', 'Rollie Funa')
        ->set('problem', '34 trays and 7 pcs of big dirty eggs returned as spoiled.');
}

describe('the form', function () {
    it('prefills the issuer and the first unit, category and sub-category', function () {
        Livewire::actingAs($this->requestor)
            ->test(Create::class)
            ->assertSet('issuedBy', 'Gab Maglalang — Requestor')
            ->assertSet('complaintType', ComplaintType::Product->value)
            ->assertSet('unitId', unitId('Eggroom'))
            ->assertSet('categoryId', categoryId('TABLE EGG', 'Production Related'))
            ->assertSet('subcategoryId', subcategoryId('TABLE EGG', 'Production Related', 'Shell Quality Defects'))
            ->assertSee(['Business line: TABLE EGG', 'assigned on submit']);
    });

    it('switches to the DOP categories when a DOP unit is chosen', function () {
        Livewire::actingAs($this->requestor)
            ->test(Create::class)
            ->set('unitId', unitId('Hatchery'))
            ->assertSee('Business line: DOP')
            ->assertSet('categoryId', categoryId('DOP', 'Production Related'))
            ->assertSet('subcategoryId', subcategoryId('DOP', 'Production Related', 'Hatchery Production & Hatch Rates'));
    });

    it('resets the sub-category when the category changes', function () {
        Livewire::actingAs($this->requestor)
            ->test(Create::class)
            ->set('categoryId', categoryId('TABLE EGG', 'Logistics & Distribution Transport'))
            ->assertSet('subcategoryId', subcategoryId('TABLE EGG', 'Logistics & Distribution Transport', 'Issuance & Delivery Errors'));
    });

    it('shows the what-to-report guidance for the selected sub-category', function () {
        Livewire::actingAs($this->requestor)
            ->test(Create::class)
            ->set('subcategoryId', subcategoryId('TABLE EGG', 'Production Related', 'Internal Quality Defects'))
            ->assertSee('What to report here: Watery/weak albumen');
    });

    it('previews deadlines from today and the category timeline', function () {
        Livewire::actingAs($this->requestor)
            ->test(Create::class)
            ->assertSee(['Oct 5, 2026 (+3d)', 'Oct 12, 2026 (+10d)'])
            ->set('unitId', unitId('Hatchery'))
            ->set('categoryId', categoryId('DOP', 'Compliance & Standards'))
            ->assertSee(['Oct 3, 2026 (+1d)', 'Oct 5, 2026 (+3d)']);
    });
});

describe('submitting', function () {
    it('files the CAR through the workflow and opens it', function () {
        filledCreateForm($this->requestor)
            ->set('complaintReceivedOn', '2026-09-30')
            ->call('submit')
            ->assertHasNoErrors()
            ->assertRedirect(route('cars.show', Car::sole()));

        expect(Car::sole())
            ->reference->toBe('CAR-2026-0001')
            ->status->toBe(CarStatus::AwaitingRelease)
            ->requestor_id->toBe($this->requestor->id)
            ->complainant->toBe('Rollie Funa')
            ->complaint_type->toBe(ComplaintType::Product)
            ->complaint_received_on->toDateString()->toBe('2026-09-30')
            ->response_due_on->toDateString()->toBe('2026-10-05');
    });

    it('stores attachments privately against the CAR', function () {
        Storage::fake('local');

        filledCreateForm($this->requestor)
            ->set('attachments', [
                UploadedFile::fake()->image('spoiled.jpg'),
                UploadedFile::fake()->create('customer-video.mp4', 2048, 'video/mp4'),
            ])
            ->call('submit')
            ->assertHasNoErrors();

        $attachments = Car::sole()->attachments;

        expect($attachments->pluck('original_name')->all())->toBe(['spoiled.jpg', 'customer-video.mp4'])
            ->and($attachments->first()->uploaded_by)->toBe($this->requestor->id);
        $attachments->each(fn ($attachment) => Storage::disk('local')->assertExists($attachment->path));
    });

    it('rejects file types that are not evidence', function () {
        filledCreateForm($this->requestor)
            ->set('attachments', [UploadedFile::fake()->create('script.exe', 10)])
            ->call('submit')
            ->assertHasErrors('attachments.0')
            ->assertSee('Use photos, videos (mp4, mov), PDF or Office files.');

        expect(Car::count())->toBe(0);
    });

    it('rejects files over 50 MB as soon as they are uploaded', function () {
        filledCreateForm($this->requestor)
            ->set('attachments', [UploadedFile::fake()->create('long-video.mp4', 51201, 'video/mp4')])
            ->assertHasErrors('attachments.0')
            ->assertSet('attachments', []);
    });

    it('requires a complainant and problem details', function () {
        Livewire::actingAs($this->requestor)
            ->test(Create::class)
            ->call('submit')
            ->assertHasErrors(['complainant' => 'required', 'problem' => 'required']);
    });

    it('rejects a complaint date in the future', function () {
        filledCreateForm($this->requestor)
            ->set('complaintReceivedOn', '2026-10-03')
            ->call('submit')
            ->assertHasErrors('complaintReceivedOn')
            ->assertSee('The complaint cannot have been received in the future.');
    });

    it('rejects a sub-category from a different category', function () {
        filledCreateForm($this->requestor)
            ->set('subcategoryId', subcategoryId('DOP', 'Sales & Order Management', 'Booking & Sales Cancellations'))
            ->call('submit')
            ->assertHasErrors(['subcategoryId' => 'exists']);

        expect(Car::count())->toBe(0);
    });
});

describe('correcting a returned CAR', function () {
    beforeEach(function () {
        $category = Category::find(categoryId('TABLE EGG', 'Production Related'));
        $this->returned = Car::factory()->status(CarStatus::ReturnedToRequestor)->create([
            'requestor_id' => $this->requestor->id,
            'issued_on' => '2026-09-28',
            'issued_to_unit_id' => unitId('PFC Production'),
            'category_id' => $category->id,
            'subcategory_id' => subcategoryId('TABLE EGG', 'Production Related', 'Internal Quality Defects'),
        ]);
        $this->returned->events()->create(['round' => 1, 'action' => 'reject', 'from_status' => 'awaiting_release', 'to_status' => 'returned_to_requestor', 'actor_id' => User::factory()->role(Role::RequestorApprover)->create()->id, 'note' => 'Attach the dispatch form.', 'created_at' => now()]);
    });

    it('loads the CAR with the reason it was returned', function () {
        Livewire::actingAs($this->requestor)
            ->test(Create::class, ['car' => $this->returned])
            ->assertSet('complainant', $this->returned->complainant)
            ->assertSee(['Returned by the Requestor Approver: Attach the dispatch form.', $this->returned->reference, 'Resubmit for release']);
    });

    it('saves the corrections and resubmits for release, keeping the issued date', function () {
        Livewire::actingAs($this->requestor)
            ->test(Create::class, ['car' => $this->returned])
            ->set('categoryId', categoryId('TABLE EGG', 'Compliance & Standards'))
            ->set('subcategoryId', subcategoryId('TABLE EGG', 'Compliance & Standards', 'Storage & Handling'))
            ->set('problem', 'Updated: dispatch form shows the eggs were 3 days old.')
            ->call('submit')
            ->assertHasNoErrors()
            ->assertRedirect(route('cars.show', $this->returned));

        expect($this->returned->fresh())
            ->status->toBe(CarStatus::AwaitingRelease)
            ->problem_details->toBe('Updated: dispatch form shows the eggs were 3 days old.')
            ->issued_on->toDateString()->toBe('2026-09-28')
            ->response_due_on->toDateString()->toBe('2026-09-29')
            ->implementation_due_on->toDateString()->toBe('2026-10-03');
    });

    it('forbids correcting someone else\'s CAR', function () {
        $otherRequestor = User::factory()->role(Role::Requestor)->create();

        Livewire::actingAs($otherRequestor)
            ->test(Create::class, ['car' => $this->returned])
            ->assertForbidden();
    });

    it('forbids correcting a CAR that is not returned', function () {
        $released = Car::factory()->status(CarStatus::AwaitingResponder)->create(['requestor_id' => $this->requestor->id]);

        $this->actingAs($this->requestor)->get(route('cars.edit', $released))->assertForbidden();
    });
});

describe('when saving fails', function () {
    it('submits nothing, removes the files already saved and keeps the form for another try', function () {
        Storage::fake('local');
        Notification::fake();
        Attachment::creating(function (Attachment $attachment): void {
            if ($attachment->original_name === 'second.jpg') {
                throw new RuntimeException('Disk full');
            }
        });

        filledCreateForm($this->requestor)
            ->set('attachments', [UploadedFile::fake()->image('first.jpg'), UploadedFile::fake()->image('second.jpg')])
            ->call('submit')
            ->assertHasErrors('submission')
            ->assertSee('nothing was submitted')
            ->assertNoRedirect()
            ->assertCount('attachments', 2)
            ->assertSet('complainant', 'Rollie Funa');

        expect(Car::count())->toBe(0)
            ->and(Attachment::count())->toBe(0)
            ->and(Storage::disk('local')->allFiles('cars'))->toBe([]);
        Notification::assertNothingSent();
    });

    it('leaves a returned CAR untouched when its resubmission fails', function () {
        Storage::fake('local');
        $car = Car::factory()->status(CarStatus::ReturnedToRequestor)->create(['requestor_id' => $this->requestor->id]);
        Attachment::creating(fn () => throw new RuntimeException('Disk full'));

        Livewire::actingAs($this->requestor)
            ->test(Create::class, ['car' => $car])
            ->set('complainant', 'Corrected Complainant')
            ->set('attachments', [UploadedFile::fake()->image('more-proof.jpg')])
            ->call('submit')
            ->assertHasErrors('submission');

        expect($car->fresh())
            ->status->toBe(CarStatus::ReturnedToRequestor)
            ->complainant->not->toBe('Corrected Complainant')
            ->and(Storage::disk('local')->allFiles())->toBe([]);
    });
});
