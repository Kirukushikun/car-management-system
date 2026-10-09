<?php

namespace App\Livewire\Cars;

use App\Enums\CarAction;
use App\Enums\ComplaintType;
use App\Livewire\Concerns\GuardsSubmissions;
use App\Models\Attachment;
use App\Models\Car;
use App\Models\Category;
use App\Models\Farm;
use App\Models\IssuedToUnit;
use App\Models\Subcategory;
use App\Services\CarWorkflow;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

/**
 * Phase I form (Steps 1–6). Files a new CAR, or — on a CAR returned by the Requestor Approver —
 * corrects its details and resubmits it for release. Issued To drives the business line, which
 * drives the categories, sub-categories and the auto-calculated deadlines.
 */
class Create extends Component
{
    use GuardsSubmissions, WithFileUploads;

    /**
     * The returned CAR being corrected; null when filing a new one.
     */
    public ?Car $car = null;

    public ?int $farmId = null;

    public ?int $unitId = null;

    public ?int $categoryId = null;

    public ?int $subcategoryId = null;

    public string $complaintType = '';

    public ?string $complaintReceivedOn = null;

    public string $issuedBy = '';

    public string $complainant = '';

    public string $problem = '';

    /**
     * @var array<int, TemporaryUploadedFile>
     */
    public array $attachments = [];

    public function mount(?Car $car = null): void
    {
        if ($car?->exists) {
            $this->authorize('act', [$car, CarAction::Resubmit]);
            $this->fillFrom($car);

            return;
        }

        $this->authorize('create', Car::class);

        $this->car = null;
        $this->complaintType = ComplaintType::Product->value;
        $this->issuedBy = auth()->user()->name.' — '.auth()->user()->role->label();
        $this->farmId = Farm::orderBy('id')->value('id');
        $this->unitId = IssuedToUnit::orderBy('id')->value('id');
        $this->resetCategory();
    }

    public function updatedUnitId(): void
    {
        $this->resetCategory();
    }

    public function updatedCategoryId(): void
    {
        $this->subcategoryId = $this->subcategories()->first()?->id;
    }

    public function removeAttachment(int $index): void
    {
        unset($this->attachments[$index]);
        $this->attachments = array_values($this->attachments);
    }

    public function submit(CarWorkflow $workflow): void
    {
        $this->car
            ? $this->authorize('act', [$this->car, CarAction::Resubmit])
            : $this->authorize('create', Car::class);

        $this->validate();

        $details = [
            'farm_id' => $this->farmId,
            'issued_to_unit_id' => $this->unitId,
            'category_id' => $this->categoryId,
            'subcategory_id' => $this->subcategoryId,
            'complaint_type' => ComplaintType::from($this->complaintType),
            'complaint_received_on' => $this->complaintReceivedOn ?: null,
            'issued_by' => $this->issuedBy,
            'complainant' => $this->complainant,
            'problem_details' => $this->problem,
        ];

        $car = $this->guardSubmission(function () use ($workflow, $details): Car {
            $car = $this->car
                ? $workflow->resubmit($this->car, auth()->user(), $details)
                : $workflow->submit(auth()->user(), $details);

            foreach ($this->attachments as $file) {
                Attachment::store($car, $file, Attachment::PROBLEM_EVIDENCE, auth()->user());
            }

            return $car;
        });

        if ($car === null) {
            return;
        }

        session()->flash('status', $this->car
            ? "{$car->reference} was resubmitted for release."
            : "{$car->reference} was submitted. The Requestor Approver reviews it before it is released to the Responder.");

        $this->redirectRoute('cars.show', $car, navigate: true);
    }

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        return [
            'farmId' => ['required', Rule::exists('farms', 'id')],
            'unitId' => ['required', Rule::exists('issued_to_units', 'id')],
            'categoryId' => ['required', Rule::exists('categories', 'id')->where('business_line_id', $this->unit()?->business_line_id)],
            'subcategoryId' => ['required', Rule::exists('subcategories', 'id')->where('category_id', $this->categoryId)],
            'complaintType' => ['required', Rule::enum(ComplaintType::class)],
            'complaintReceivedOn' => ['nullable', 'date', 'before_or_equal:today'],
            'issuedBy' => ['required', 'string', 'max:255'],
            'complainant' => ['required', 'string', 'max:255'],
            'problem' => ['required', 'string', 'min:10'],
            'attachments' => ['array', 'max:10'],
            'attachments.*' => ['file', 'max:'.Attachment::MAX_KILOBYTES, 'extensions:'.implode(',', Attachment::ALLOWED_EXTENSIONS)],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function messages(): array
    {
        return [
            'categoryId.exists' => 'Pick a category that belongs to the selected unit\'s business line.',
            'subcategoryId.exists' => 'Pick a sub-category that belongs to the selected category.',
            'complaintReceivedOn.before_or_equal' => 'The complaint cannot have been received in the future.',
            'attachments.max' => 'Attach at most 10 files.',
            'attachments.*.max' => 'Each file must be 50 MB or smaller.',
            'attachments.*.extensions' => 'Use photos, videos (mp4, mov), PDF or Office files.',
        ];
    }

    public function render(): View
    {
        $category = $this->category();
        $issuedOn = $this->car?->issued_on ?? CarbonImmutable::today();

        return view('livewire.cars.create', [
            'farms' => Farm::orderBy('id')->get(),
            'units' => IssuedToUnit::orderBy('id')->get(),
            'line' => $this->unit()?->businessLine->name,
            'categories' => $this->categories(),
            'subcategories' => $this->subcategories(),
            'subcategory' => $this->subcategories()->firstWhere('id', $this->subcategoryId),
            'complaintTypes' => ComplaintType::cases(),
            'issuedOn' => $issuedOn,
            'category' => $category,
            'responseDue' => $category ? $issuedOn->addDays($category->response_days) : null,
            'implementationDue' => $category ? $issuedOn->addDays($category->implementation_days) : null,
            'existingAttachments' => $this->car?->attachments ?? new Collection,
            'returnReason' => $this->car?->events()->where('action', CarAction::Reject)->latest('id')->value('note'),
        ])->title($this->car ? "Correct {$this->car->reference}" : 'New CAR');
    }

    private function fillFrom(Car $car): void
    {
        $this->farmId = $car->farm_id;
        $this->unitId = $car->issued_to_unit_id;
        $this->categoryId = $car->category_id;
        $this->subcategoryId = $car->subcategory_id;
        $this->complaintType = $car->complaint_type->value;
        $this->complaintReceivedOn = $car->complaint_received_on?->toDateString();
        $this->issuedBy = $car->issued_by;
        $this->complainant = $car->complainant;
        $this->problem = $car->problem_details;
    }

    private function resetCategory(): void
    {
        $this->categoryId = $this->categories()->first()?->id;
        $this->updatedCategoryId();
    }

    private function unit(): ?IssuedToUnit
    {
        return IssuedToUnit::with('businessLine')->find($this->unitId);
    }

    private function category(): ?Category
    {
        return $this->categories()->firstWhere('id', $this->categoryId);
    }

    /**
     * Categories of the selected unit's business line.
     *
     * @return Collection<int, Category>
     */
    private function categories(): Collection
    {
        return Category::where('business_line_id', $this->unit()?->business_line_id)->orderBy('id')->get();
    }

    /**
     * @return Collection<int, Subcategory>
     */
    private function subcategories(): Collection
    {
        return Subcategory::where('category_id', $this->categoryId)->orderBy('id')->get();
    }
}
