<?php

namespace App\Livewire\Cars;

use App\Enums\CarAction;
use App\Livewire\Concerns\GuardsSubmissions;
use App\Models\Attachment;
use App\Models\BusinessLine;
use App\Models\Car;
use App\Models\Category;
use App\Models\ComplaintType;
use App\Models\Farm;
use App\Models\IssuedToUnit;
use App\Services\CarWorkflow;
use App\Services\DeadlineCalculator;
use App\Support\TextLimits;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

/**
 * Phase I form (Steps 1–6). Files a new CAR, or — on a CAR returned by the Requestor Approver —
 * corrects its details and resubmits it for release. Issued To (one or more units, all from one
 * business line) drives the categories. Category and sub-category come in pairs: each pair is one
 * category (used once per CAR) with one or more of its sub-categories, and more pairs can be added.
 * The strictest category sets the auto-calculated deadlines. Only active values (see Maintenance)
 * are offered, plus any a returned CAR already carries.
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
     * @var list<int|string>
     */
    public array $complaintTypes = [];

    public ?string $complaintReceivedOn = null;

    public string $issuedBy = '';

    public string $complainant = '';

    public string $problem = '';

    /**
     * Optional: an initial action the Complainant's side already took before filing.
     */
    public string $initialAction = '';

    public ?string $initialActionStartsOn = null;

    public ?string $initialActionEndsOn = null;

    public string $initialActionResponsible = '';

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
        $this->complaintTypes = array_values(array_filter([ComplaintType::active()->orderBy('id')->value('id')]));
        $this->issuedBy = auth()->user()->name.' — '.auth()->user()->role->label();
        $this->farmId = Farm::active()->orderBy('id')->value('id');
        $this->unitIds = array_values(array_filter([IssuedToUnit::active()->orderBy('id')->value('id')]));
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
            'complaint_type_ids' => array_values(array_map('intval', $this->complaintTypes)),
            'complaint_received_on' => $this->complaintReceivedOn ?: null,
            'issued_by' => $this->issuedBy,
            'complainant' => $this->complainant,
            'problem_details' => $this->problem,
            'initial_action' => trim($this->initialAction) ?: null,
            'initial_action_starts_on' => $this->initialActionStartsOn ?: null,
            'initial_action_ends_on' => $this->initialActionEndsOn ?: null,
            'initial_action_responsible' => trim($this->initialActionResponsible) ?: null,
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
            : "{$car->reference} was submitted. The Complainant Approver reviews it before it is released to the Concerned Department.");

        $this->redirectRoute('cars.show', $car, navigate: true);
    }

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        $rules = [
            'farmId' => ['required', $this->offerable('farms', 'farms')],
            'unitIds' => ['required', 'array', 'min:1', $this->unitsShareOneLine(...)],
            'unitIds.*' => ['distinct', $this->offerable('issued_to_units', 'units')],
            'pairs' => ['required', 'array', 'min:1'],
            'pairs.*.category_id' => ['required', 'distinct', $this->offerable('categories', 'categories')->where('business_line_id', $this->lineId())],
            'pairs.*.subcategory_ids' => ['required', 'array', 'min:1'],
            'complaintTypes' => ['required', 'array', 'min:1'],
            'complaintTypes.*' => ['distinct', $this->offerable('complaint_types', 'complaintTypes')],
            'complaintReceivedOn' => ['nullable', 'date', 'before_or_equal:today'],
            'issuedBy' => ['required', 'string', 'max:'.TextLimits::SHORT],
            'complainant' => ['required', 'string', 'max:'.TextLimits::SHORT],
            'problem' => ['required', 'string', 'min:10', 'max:'.TextLimits::PROBLEM],
            'initialAction' => ['nullable', 'string', 'max:'.TextLimits::LONG, 'required_with:initialActionStartsOn,initialActionEndsOn,initialActionResponsible'],
            'initialActionStartsOn' => ['nullable', 'date', 'before_or_equal:today'],
            'initialActionEndsOn' => ['nullable', 'date', 'after_or_equal:initialActionStartsOn'],
            'initialActionResponsible' => ['nullable', 'string', 'max:'.TextLimits::SHORT],
            'attachments' => ['array', 'max:10'],
            'attachments.*' => ['file', 'max:'.Attachment::MAX_KILOBYTES, 'extensions:'.implode(',', Attachment::ALLOWED_EXTENSIONS)],
        ];

        foreach ($this->pairs as $index => $pair) {
            $rules["pairs.{$index}.subcategory_ids.*"] = ['distinct', $this->offerable('subcategories', 'subcategories')->where('category_id', (int) $pair['category_id'])];
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
            'farmId.exists' => 'Pick an active farm.',
            'unitIds.*.exists' => 'Pick active units.',
            'complaintTypes.*.exists' => 'Pick active types of complaint.',
            'pairs.*.category_id.exists' => 'Pick a category that belongs to the selected units\' business line.',
            'pairs.*.subcategory_ids.required' => 'Pick at least one sub-category.',
            'pairs.*.subcategory_ids.*.exists' => 'Pick sub-categories that belong to this pair\'s category.',
            'complaintReceivedOn.before_or_equal' => 'The complaint cannot have been received in the future.',
            'initialAction.required_with' => 'Describe the action taken, or clear its dates and responsible person.',
            'initialActionStartsOn.before_or_equal' => 'An action already taken cannot start in the future.',
            'initialActionEndsOn.after_or_equal' => 'The end date cannot be before the start date.',
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
            'farms' => Farm::offerable($this->kept('farms'))->orderBy('id')->get(),
            'unitOptions' => IssuedToUnit::offerable($this->kept('units'))->with('businessLine')->orderBy('business_line_id')->orderBy('id')->get()
                ->map(fn (IssuedToUnit $unit): array => ['value' => $unit->id, 'label' => $unit->name, 'group' => $unit->businessLine->name])
                ->all(),
            'line' => BusinessLine::find($this->lineId())?->name,
            'categories' => $categories,
            'guideLines' => BusinessLine::with([
                'categories' => fn ($query) => $query->active()->orderBy('id'),
                'categories.subcategories' => fn ($query) => $query->active()->orderBy('id'),
            ])->orderBy('id')->get(),
            'usedCategoryIds' => $this->usedCategoryIds(),
            'complaintTypeOptions' => ComplaintType::offerable($this->kept('complaintTypes'))->orderBy('id')->get()
                ->map(fn (ComplaintType $type): array => ['value' => $type->id, 'label' => $type->name, 'group' => null])
                ->all(),
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
        $this->complaintTypes = $car->complaintTypes->modelKeys();
        $this->complaintReceivedOn = $car->complaint_received_on?->toDateString();
        $this->issuedBy = $car->issued_by;
        $this->complainant = $car->complainant;
        $this->problem = $car->problem_details;
        $this->initialAction = (string) $car->initial_action;
        $this->initialActionStartsOn = $car->initial_action_starts_on?->toDateString();
        $this->initialActionEndsOn = $car->initial_action_ends_on?->toDateString();
        $this->initialActionResponsible = (string) $car->initial_action_responsible;
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
     * Ids of the values the returned CAR already carries, which stay valid even if deactivated
     * since. Nothing is kept on a new CAR.
     *
     * @param  'farms'|'units'|'categories'|'subcategories'|'complaintTypes'  $kind
     * @return list<int>
     */
    private function kept(string $kind): array
    {
        if ($this->car === null) {
            return [];
        }

        return match ($kind) {
            'farms' => [$this->car->farm_id],
            'units' => $this->car->issuedToUnits->modelKeys(),
            'categories' => $this->car->subcategories->pluck('category_id')->unique()->values()->all(),
            'subcategories' => $this->car->subcategories->modelKeys(),
            'complaintTypes' => $this->car->complaintTypes->modelKeys(),
        };
    }

    /**
     * An "exists" rule that accepts active values and the ones the CAR already carries.
     *
     * @param  'farms'|'units'|'categories'|'subcategories'|'complaintTypes'  $kind
     */
    private function offerable(string $table, string $kind): Exists
    {
        $kept = $this->kept($kind);

        return Rule::exists($table, 'id')->where(fn ($query) => $query->where('is_active', true)->when($kept !== [], fn ($query) => $query->orWhereIn('id', $kept)));
    }

    /**
     * Categories of the selected units' business line, with their sub-categories — the active
     * ones, plus any the returned CAR already carries.
     *
     * @return Collection<int, Category>
     */
    private function categories(): Collection
    {
        return Category::offerable($this->kept('categories'))
            ->with(['subcategories' => fn ($query) => $query->offerable($this->kept('subcategories'))->orderBy('id')])
            ->where('business_line_id', $this->lineId())
            ->orderBy('id')
            ->get();
    }
}
