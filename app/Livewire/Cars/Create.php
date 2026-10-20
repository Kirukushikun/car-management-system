<?php

namespace App\Livewire\Cars;

use App\Enums\CarAction;
use App\Enums\ComplaintType;
use App\Livewire\Concerns\GuardsSubmissions;
use App\Models\Attachment;
use App\Models\BusinessLine;
use App\Models\Car;
use App\Models\Category;
use App\Models\Farm;
use App\Models\IssuedToUnit;
use App\Services\CarWorkflow;
use App\Services\DeadlineCalculator;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

/**
 * Phase I form (Steps 1–6). Files a new CAR, or — on a CAR returned by the Requestor Approver —
 * corrects its details and resubmits it for release. Issued To (one or more units, all from one
 * business line) drives the categories. Category and sub-category come in pairs: each pair is one
 * category (used once per CAR) with one or more of its sub-categories, and more pairs can be added.
 * The strictest category sets the auto-calculated deadlines.
 */
class Create extends Component
{
    use GuardsSubmissions, WithFileUploads;

    /**
     * The returned CAR being corrected; null when filing a new one.
     */
    public ?Car $car = null;

    public ?int $farmId = null;

    /**
     * @var list<int|string>
     */
    public array $unitIds = [];

    /**
     * Category / sub-category pairs: one category each, with the sub-categories picked under it.
     *
     * @var list<array{category_id: int|string|null, subcategory_ids: list<int|string>}>
     */
    public array $pairs = [];

    /**
     * @var list<string>
     */
    public array $complaintTypes = [];

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
        $this->complaintTypes = [ComplaintType::Product->value];
        $this->issuedBy = auth()->user()->name.' — '.auth()->user()->role->label();
        $this->farmId = Farm::orderBy('id')->value('id');
        $this->unitIds = [IssuedToUnit::orderBy('id')->value('id')];
        $this->resetPairs();
    }

    /**
     * Categories follow the units' business line; picking units from another line starts the
     * pairs over.
     */
    public function updatedUnitIds(): void
    {
        $line = $this->lineId();

        if ($line !== null && Category::whereKey($this->pairs[0]['category_id'] ?? null)->value('business_line_id') !== $line) {
            $this->resetPairs();
        }
    }

    /**
     * A pair's sub-categories belong to its category, so changing the category clears them.
     */
    public function updatedPairs(mixed $value, string $key): void
    {
        [$index, $field] = array_pad(explode('.', $key, 2), 2, null);

        if ($field === 'category_id') {
            $this->pairs[(int) $index]['subcategory_ids'] = [];
        }
    }

    /**
     * Add a pair, starting on the first category not used by another pair.
     */
    public function addPair(): void
    {
        $next = $this->categories()->first(fn (Category $category): bool => ! in_array($category->id, $this->usedCategoryIds(), true));

        if ($next !== null) {
            $this->pairs[] = ['category_id' => $next->id, 'subcategory_ids' => []];
        }
    }

    public function removePair(int $index): void
    {
        if (count($this->pairs) > 1) {
            unset($this->pairs[$index]);
            $this->pairs = array_values($this->pairs);
            $this->resetErrorBag();
        }
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
            'issued_to_unit_ids' => array_values(array_map('intval', $this->unitIds)),
            'subcategory_ids' => $this->pickedSubcategoryIds(),
            'complaint_types' => array_values(array_map(fn (string $type): ComplaintType => ComplaintType::from($type), $this->complaintTypes)),
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
        $rules = [
            'farmId' => ['required', Rule::exists('farms', 'id')],
            'unitIds' => ['required', 'array', 'min:1', $this->unitsShareOneLine(...)],
            'unitIds.*' => ['distinct', Rule::exists('issued_to_units', 'id')],
            'pairs' => ['required', 'array', 'min:1'],
            'pairs.*.category_id' => ['required', 'distinct', Rule::exists('categories', 'id')->where('business_line_id', $this->lineId())],
            'pairs.*.subcategory_ids' => ['required', 'array', 'min:1'],
            'complaintTypes' => ['required', 'array', 'min:1'],
            'complaintTypes.*' => ['distinct', Rule::enum(ComplaintType::class)],
            'complaintReceivedOn' => ['nullable', 'date', 'before_or_equal:today'],
            'issuedBy' => ['required', 'string', 'max:255'],
            'complainant' => ['required', 'string', 'max:255'],
            'problem' => ['required', 'string', 'min:10'],
            'attachments' => ['array', 'max:10'],
            'attachments.*' => ['file', 'max:'.Attachment::MAX_KILOBYTES, 'extensions:'.implode(',', Attachment::ALLOWED_EXTENSIONS)],
        ];

        foreach ($this->pairs as $index => $pair) {
            $rules["pairs.{$index}.subcategory_ids.*"] = ['distinct', Rule::exists('subcategories', 'id')->where('category_id', (int) $pair['category_id'])];
        }

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    protected function messages(): array
    {
        return [
            'unitIds.required' => 'Pick at least one unit the CAR is issued to.',
            'complaintTypes.required' => 'Pick at least one type of complaint.',
            'pairs.*.category_id.required' => 'Pick a category.',
            'pairs.*.category_id.distinct' => 'This category is already in another pair — add its sub-categories there.',
            'pairs.*.category_id.exists' => 'Pick a category that belongs to the selected units\' business line.',
            'pairs.*.subcategory_ids.required' => 'Pick at least one sub-category.',
            'pairs.*.subcategory_ids.*.exists' => 'Pick sub-categories that belong to this pair\'s category.',
            'complaintReceivedOn.before_or_equal' => 'The complaint cannot have been received in the future.',
            'attachments.max' => 'Attach at most 10 files.',
            'attachments.*.max' => 'Each file must be 50 MB or smaller.',
            'attachments.*.extensions' => 'Use photos, videos (mp4, mov), PDF or Office files.',
        ];
    }

    public function render(DeadlineCalculator $deadlines): View
    {
        $issuedOn = $this->car?->issued_on ?? CarbonImmutable::today();
        $categories = $this->categories();
        $picked = $categories->whereIn('id', $this->usedCategoryIds());

        return view('livewire.cars.create', [
            'farms' => Farm::orderBy('id')->get(),
            'unitOptions' => IssuedToUnit::with('businessLine')->orderBy('business_line_id')->orderBy('id')->get()
                ->map(fn (IssuedToUnit $unit): array => ['value' => $unit->id, 'label' => $unit->name, 'group' => $unit->businessLine->name])
                ->all(),
            'line' => BusinessLine::find($this->lineId())?->name,
            'categories' => $categories,
            'guideLines' => BusinessLine::with([
                'categories' => fn ($query) => $query->orderBy('id'),
                'categories.subcategories' => fn ($query) => $query->orderBy('id'),
            ])->orderBy('id')->get(),
            'usedCategoryIds' => $this->usedCategoryIds(),
            'complaintTypeOptions' => array_map(fn (ComplaintType $type): array => ['value' => $type->value, 'label' => $type->label(), 'group' => null], ComplaintType::cases()),
            'issuedOn' => $issuedOn,
            'deadline' => $picked->isNotEmpty() ? $deadlines->forCategories($picked, $issuedOn) : null,
            'existingAttachments' => $this->car?->attachments ?? new Collection,
            'returnReason' => $this->car?->events()->where('action', CarAction::Reject)->latest('id')->value('note'),
        ])->title($this->car ? "Correct {$this->car->reference}" : 'New CAR');
    }

    private function fillFrom(Car $car): void
    {
        $this->farmId = $car->farm_id;
        $this->unitIds = $car->issuedToUnits->pluck('id')->all();
        $this->pairs = $car->classification()
            ->map(fn (array $pair): array => ['category_id' => $pair['category']->id, 'subcategory_ids' => $pair['subcategories']->pluck('id')->all()])
            ->all();
        $this->complaintTypes = $car->complaint_types->map(fn (ComplaintType $type): string => $type->value)->all();
        $this->complaintReceivedOn = $car->complaint_received_on?->toDateString();
        $this->issuedBy = $car->issued_by;
        $this->complainant = $car->complainant;
        $this->problem = $car->problem_details;
    }

    private function resetPairs(): void
    {
        $this->pairs = [['category_id' => $this->categories()->first()?->id, 'subcategory_ids' => []]];
    }

    /**
     * @return list<int>
     */
    private function usedCategoryIds(): array
    {
        return array_values(array_filter(array_map(fn (array $pair): int => (int) $pair['category_id'], $this->pairs)));
    }

    /**
     * @return list<int>
     */
    private function pickedSubcategoryIds(): array
    {
        return array_values(array_unique(array_map('intval', array_merge(...array_column($this->pairs, 'subcategory_ids')))));
    }

    /**
     * The business line of the picked units (they must share one).
     */
    private function lineId(): ?int
    {
        return IssuedToUnit::whereKey(array_map('intval', $this->unitIds))->orderBy('id')->value('business_line_id');
    }

    /**
     * All picked units must come from one business line, since the category list is per line.
     */
    private function unitsShareOneLine(string $attribute, mixed $value, Closure $fail): void
    {
        if (IssuedToUnit::whereKey(array_map('intval', (array) $value))->distinct()->count('business_line_id') > 1) {
            $fail('Pick units from one business line only — TABLE EGG or DOP.');
        }
    }

    /**
     * Categories of the selected units' business line, with their sub-categories.
     *
     * @return Collection<int, Category>
     */
    private function categories(): Collection
    {
        return Category::with(['subcategories' => fn ($query) => $query->orderBy('id')])
            ->where('business_line_id', $this->lineId())
            ->orderBy('id')
            ->get();
    }
}
