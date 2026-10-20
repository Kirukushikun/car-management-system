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
    $this->requestor = User::factory()->role(Role::Requestor)->create(['name' => 'John Doe']);
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
        ->set('unitIds', [unitId('PFC Production')])
        ->set('pairs.0.subcategory_ids', [subcategoryId('TABLE EGG', 'Production Related', 'Internal Quality Defects')])
        ->set('complainant', 'Rollie Funa')
        ->set('problem', '34 trays and 7 pcs of big dirty eggs returned as spoiled.');
}

describe('the form', function () {
    it('prefills the issuer, the first unit and one pair on the first category', function () {
        Livewire::actingAs($this->requestor)
            ->test(Create::class)
            ->assertSet('issuedBy', 'John Doe — Complainant')
            ->assertSet('complaintTypes', [ComplaintType::Product->value])
            ->assertSet('unitIds', [unitId('Eggroom')])
            ->assertSet('pairs', [['category_id' => categoryId('TABLE EGG', 'Production Related'), 'subcategory_ids' => []]])
            ->assertSee(['Business line: TABLE EGG', 'assigned on submit', 'Shell Quality Defects']);
    });

    it('switches to the DOP categories when a DOP unit is chosen', function () {
        Livewire::actingAs($this->requestor)
            ->test(Create::class)
            ->call('addPair')
            ->set('unitIds', [unitId('Hatchery')])
            ->assertSee(['Business line: DOP', 'Hatchery Production &amp; Hatch Rates'], false)
            ->assertSet('pairs', [['category_id' => categoryId('DOP', 'Production Related'), 'subcategory_ids' => []]]);
    });

    it('keeps the pairs when more units from the same business line are added', function () {
        Livewire::actingAs($this->requestor)
            ->test(Create::class)
            ->set('pairs.0.category_id', categoryId('TABLE EGG', 'Compliance & Standards'))
            ->set('unitIds', [unitId('Eggroom'), unitId('Egg Grading')])
            ->assertSet('pairs.0.category_id', categoryId('TABLE EGG', 'Compliance & Standards'));
    });

    it('adds a pair on the next unused category and offers each category in one pair only', function () {
        Livewire::actingAs($this->requestor)
            ->test(Create::class)
            ->call('addPair')
            ->assertSet('pairs.1.category_id', categoryId('TABLE EGG', 'Compliance & Standards'))
            ->assertSeeHtml('id="pair-1-category"')
            ->call('addPair')
            ->assertSet('pairs.2.category_id', categoryId('TABLE EGG', 'Logistics & Distribution Transport'))
            ->assertDontSeeHtml('wire:click="addPair"')
            ->call('removePair', 1)
            ->assertCount('pairs', 2)
            ->assertSet('pairs.1.category_id', categoryId('TABLE EGG', 'Logistics & Distribution Transport'))
            ->assertSeeHtml('wire:click="addPair"');
    });

    it('does not offer a category that another pair already uses', function () {
        $component = Livewire::actingAs($this->requestor)->test(Create::class)->call('addPair');
        $production = categoryId('TABLE EGG', 'Production Related');
        $compliance = categoryId('TABLE EGG', 'Compliance & Standards');

        $html = $component->html();
        $firstSelect = str($html)->after('id="pair-0-category"')->before('</select>');
        $secondSelect = str($html)->after('id="pair-1-category"')->before('</select>');

        expect((string) $firstSelect)->toContain('value="'.$production.'"')->not->toContain('value="'.$compliance.'"')
            ->and((string) $secondSelect)->toContain('value="'.$compliance.'"')->not->toContain('value="'.$production.'"');
    });

    it('lists every unit in the issued-to dropdown, grouped by business line', function () {
        Livewire::actingAs($this->requestor)
            ->test(Create::class)
            ->assertSeeInOrder(['Issued to (operational units)', 'TABLE EGG', 'Eggroom', 'Table Egg Logistics', 'DOP', 'Hatchery', 'DOP Logistics']);
    });

    it('clears a pair\'s sub-categories when its category changes', function () {
        Livewire::actingAs($this->requestor)
            ->test(Create::class)
            ->set('pairs.0.subcategory_ids', [subcategoryId('TABLE EGG', 'Production Related', 'Internal Quality Defects')])
            ->set('pairs.0.category_id', categoryId('TABLE EGG', 'Logistics & Distribution Transport'))
            ->assertSet('pairs.0.subcategory_ids', []);
    });

    it('has a two-tab Phase I guide opened from the page header: an overview and the category reference', function () {
        Livewire::actingAs($this->requestor)
            ->test(Create::class)
            ->assertSeeHtml('open-guide')
            ->assertSeeInOrder([
                'Phase I guide',
                'Overview', 'Categories',
                'You file the CAR', 'The Complainant Approver reviews it', 'Released to the farm — or back to you',
                'TABLE EGG', 'DOP',
                'Production Related', '3d to respond · 10d to implement',
                'Internal Quality Defects', 'Watery/weak albumen',
                'Compliance &amp; Standards', '1d to respond · 5d to implement',
                'Hatchery Production &amp; Hatch Rates',
            ], false)
            ->assertDontSee(['The fields', 'What happens in Phase I']);
    });

    it('puts a short tip at the right of each field label', function () {
        Livewire::actingAs($this->requestor)
            ->test(Create::class)
            ->assertSeeInOrder([
                'Farm / site', 'The farm whose Concerned Department must answer this CAR.',
                'Issued to (operational units)', 'all from one business line',
                'Category', 'Each category can be used once',
                'Sub-category', 'One or more sub-categories of this category.',
                'Type of complaint', 'pick all that apply',
                'Complaint received on (optional)', 'Leave blank if it was today.',
                'Issued by', 'Change it only if you are filing for someone else.',
                'Complainant', 'a customer, a distributor or internal audit',
                'Details of the problem', 'the Concerned Department works from this',
                'File attachments (optional)', 'up to 10 files, 50 MB each',
            ]);
    });

    it('no longer stacks what-to-report notes under the picked sub-categories', function () {
        Livewire::actingAs($this->requestor)
            ->test(Create::class)
            ->set('pairs.0.subcategory_ids', [subcategoryId('TABLE EGG', 'Production Related', 'Internal Quality Defects')])
            ->assertDontSee('What to report under Internal Quality Defects');
    });

    it('previews deadlines from today and the strictest picked category', function () {
        Livewire::actingAs($this->requestor)
            ->test(Create::class)
            ->assertSee(['Oct 5, 2026 (+3d)', 'Oct 12, 2026 (+10d)'])
            ->call('addPair')
            ->assertSee(['Oct 3, 2026 (+1d)', 'Oct 7, 2026 (+5d)', 'with several categories, the shortest applies'])
            ->set('unitIds', [unitId('Hatchery')])
            ->set('pairs.0.category_id', categoryId('DOP', 'Compliance & Standards'))
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

    it('files one CAR for several units', function () {
        filledCreateForm($this->requestor)
            ->set('unitIds', [unitId('PFC Production'), unitId('Eggroom')])
            ->call('submit')
            ->assertHasNoErrors();

        expect(Car::sole()->issuedToUnits->pluck('name')->all())->toBe(['Eggroom', 'PFC Production']);
    });

    it('requires at least one unit', function () {
        filledCreateForm($this->requestor)
            ->set('unitIds', [])
            ->call('submit')
            ->assertHasErrors(['unitIds' => 'required'])
            ->assertSee('Pick at least one unit the CAR is issued to.');

        expect(Car::count())->toBe(0);
    });

    it('rejects units from both business lines', function () {
        filledCreateForm($this->requestor)
            ->set('unitIds', [unitId('PFC Production'), unitId('Hatchery')])
            ->call('submit')
            ->assertHasErrors('unitIds')
            ->assertSee('Pick units from one business line only — TABLE EGG or DOP.');

        expect(Car::count())->toBe(0);
    });

    it('rejects a sub-category from a different category', function () {
        filledCreateForm($this->requestor)
            ->set('pairs.0.subcategory_ids', [subcategoryId('DOP', 'Sales & Order Management', 'Booking & Sales Cancellations')])
            ->call('submit')
            ->assertHasErrors(['pairs.0.subcategory_ids.0' => 'exists']);

        expect(Car::count())->toBe(0);
    });

    it('files several complaint types', function () {
        filledCreateForm($this->requestor)
            ->set('complaintTypes', [ComplaintType::Product->value, ComplaintType::Other->value])
            ->call('submit')
            ->assertHasNoErrors();

        expect(Car::sole()->complaint_types->all())->toBe([ComplaintType::Product, ComplaintType::Other])
            ->and(Car::sole()->complaintTypeLabels())->toBe('Product Nonconformity, Others');
    });

    it('requires at least one complaint type', function () {
        filledCreateForm($this->requestor)
            ->set('complaintTypes', [])
            ->call('submit')
            ->assertHasErrors(['complaintTypes' => 'required'])
            ->assertSee('Pick at least one type of complaint.');

        expect(Car::count())->toBe(0);
    });

    it('requires at least one sub-category in every pair', function () {
        filledCreateForm($this->requestor)
            ->call('addPair')
            ->call('submit')
            ->assertHasErrors(['pairs.1.subcategory_ids' => 'required'])
            ->assertSee('Pick at least one sub-category.');

        expect(Car::count())->toBe(0);
    });

    it('rejects the same category in two pairs', function () {
        filledCreateForm($this->requestor)
            ->call('addPair')
            ->set('pairs.1.category_id', categoryId('TABLE EGG', 'Production Related'))
            ->set('pairs.1.subcategory_ids', [subcategoryId('TABLE EGG', 'Production Related', 'Shell Quality Defects')])
            ->call('submit')
            ->assertHasErrors('pairs.1.category_id')
            ->assertSee('This category is already in another pair');
    });

    it('files several pairs with several sub-categories each', function () {
        filledCreateForm($this->requestor)
            ->set('pairs.0.subcategory_ids', [
                subcategoryId('TABLE EGG', 'Production Related', 'Internal Quality Defects'),
                subcategoryId('TABLE EGG', 'Production Related', 'Shell Quality Defects'),
            ])
            ->call('addPair')
            ->set('pairs.1.subcategory_ids', [subcategoryId('TABLE EGG', 'Compliance & Standards', 'Storage & Handling')])
            ->call('submit')
            ->assertHasNoErrors();

        $car = Car::sole();

        expect($car->subcategories->pluck('name')->all())->toBe(['Shell Quality Defects', 'Internal Quality Defects', 'Storage & Handling'])
            ->and($car->categoryNames())->toBe('Production Related, Compliance & Standards')
            ->and($car->response_days)->toBe(1)
            ->and($car->implementation_days)->toBe(5);
    });
});

describe('correcting a returned CAR', function () {
    beforeEach(function () {
        $this->returned = Car::factory()->status(CarStatus::ReturnedToRequestor)
            ->issuedTo(IssuedToUnit::whereIn('name', ['PFC Production', 'Eggroom'])->get())
            ->classifiedAs(Subcategory::whereKey([
                subcategoryId('TABLE EGG', 'Production Related', 'Internal Quality Defects'),
                subcategoryId('TABLE EGG', 'Production Related', 'Shell Quality Defects'),
            ])->get())
            ->create([
                'requestor_id' => $this->requestor->id,
                'issued_on' => '2026-09-28',
            ]);
        $this->returned->events()->create(['round' => 1, 'action' => 'reject', 'from_status' => 'awaiting_release', 'to_status' => 'returned_to_requestor', 'actor_id' => User::factory()->role(Role::RequestorApprover)->create()->id, 'note' => 'Attach the dispatch form.', 'created_at' => now()]);
    });

    it('loads the CAR with the reason it was returned', function () {
        Livewire::actingAs($this->requestor)
            ->test(Create::class, ['car' => $this->returned])
            ->assertSet('complainant', $this->returned->complainant)
            ->assertSet('unitIds', [unitId('Eggroom'), unitId('PFC Production')])
            ->assertSet('pairs', [[
                'category_id' => categoryId('TABLE EGG', 'Production Related'),
                'subcategory_ids' => [subcategoryId('TABLE EGG', 'Production Related', 'Shell Quality Defects'), subcategoryId('TABLE EGG', 'Production Related', 'Internal Quality Defects')],
            ]])
            ->assertSee(['Returned by the Complainant Approver: Attach the dispatch form.', $this->returned->reference, 'Resubmit for release']);
    });

    it('saves the corrections and resubmits for release, keeping the issued date', function () {
        Livewire::actingAs($this->requestor)
            ->test(Create::class, ['car' => $this->returned])
            ->set('pairs.0.category_id', categoryId('TABLE EGG', 'Compliance & Standards'))
            ->set('pairs.0.subcategory_ids', [subcategoryId('TABLE EGG', 'Compliance & Standards', 'Storage & Handling')])
            ->set('problem', 'Updated: dispatch form shows the eggs were 3 days old.')
            ->call('submit')
            ->assertHasNoErrors()
            ->assertRedirect(route('cars.show', $this->returned));

        expect($this->returned->fresh())
            ->status->toBe(CarStatus::AwaitingRelease)
            ->problem_details->toBe('Updated: dispatch form shows the eggs were 3 days old.')
            ->issued_on->toDateString()->toBe('2026-09-28')
            ->response_due_on->toDateString()->toBe('2026-09-29')
            ->implementation_due_on->toDateString()->toBe('2026-10-03')
            ->and($this->returned->fresh()->subcategories->pluck('name')->all())->toBe(['Storage & Handling']);
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
