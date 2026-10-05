<?php

namespace App\Livewire\Cars;

use App\Models\Category;
use App\Models\Farm;
use App\Models\IssuedToUnit;
use App\Models\Subcategory;
use App\Services\ScaffoldData;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Phase I form: Issued To drives the business line, which drives the categories, which drive
 * the sub-categories and the auto-calculated deadlines. Reference data comes from the database
 * (Phase 1); saving the CAR is a stub until Phase 3.
 */
#[Title('New CAR')]
class Create extends Component
{
    public ?int $farmId = null;

    public ?int $unitId = null;

    public ?int $categoryId = null;

    public ?int $subcategoryId = null;

    public string $issuedBy = '';

    public string $complainant = '';

    public string $problem = '';

    public ?string $notice = null;

    public function mount(): void
    {
        $this->authorize('create-cars');

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

    public function submit(): void
    {
        $this->authorize('create-cars');
        $this->validate();

        $this->notice = 'Validation passed. Saving and routing to the Requestor Approver is a stub in the UI scaffold — it is wired up in Phase 3. Nothing was saved.';
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
            'complainant' => ['required', 'string', 'max:255'],
            'problem' => ['required', 'string', 'min:10'],
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
        ];
    }

    public function render(): View
    {
        $category = $this->category();
        $issuedOn = CarbonImmutable::today();

        return view('livewire.cars.create', [
            'farms' => Farm::orderBy('id')->get(),
            'units' => IssuedToUnit::orderBy('id')->get(),
            'line' => $this->unit()?->businessLine->name,
            'categories' => $this->categories(),
            'subcategories' => $this->subcategories(),
            'subcategory' => $this->subcategories()->firstWhere('id', $this->subcategoryId),
            'reference' => ScaffoldData::nextReference(),
            'issuedOn' => $issuedOn,
            'category' => $category,
            'responseDue' => $category ? $issuedOn->addDays($category->response_days) : null,
            'implementationDue' => $category ? $issuedOn->addDays($category->implementation_days) : null,
        ]);
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
