<?php

namespace App\Livewire\Cars;

use App\Services\ScaffoldData;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Title;
use Livewire\Attributes\Validate;
use Livewire\Component;

/**
 * Phase I form: Issued To drives the business line, which drives the categories, which drive
 * the sub-categories and the auto-calculated deadlines. Submitting is a stub until Phase 3.
 */
#[Title('New CAR')]
class Create extends Component
{
    public string $farm = 'PFC';

    public string $unit = '';

    public string $category = '';

    public string $subcategory = '';

    public string $issuedBy = '';

    #[Validate('required|string|max:255')]
    public string $complainant = '';

    #[Validate('required|string|min:10')]
    public string $problem = '';

    public ?string $notice = null;

    public function mount(): void
    {
        $this->authorize('create-cars');

        $this->issuedBy = auth()->user()->name.' — '.auth()->user()->role->label();
        $this->unit = array_key_first(ScaffoldData::units());
        $this->resetCategory();
    }

    public function updatedUnit(): void
    {
        $this->resetCategory();
    }

    public function updatedCategory(): void
    {
        $this->subcategory = $this->categories()[$this->category]['subcategories'][0];
    }

    public function submit(): void
    {
        $this->authorize('create-cars');
        $this->validate();

        $this->notice = 'Validation passed. Saving and routing to the Requestor Approver is a stub in the UI scaffold — it is wired up in Phase 3. Nothing was saved.';
    }

    public function render(): View
    {
        $timeline = $this->categories()[$this->category];
        $issuedOn = CarbonImmutable::today();

        return view('livewire.cars.create', [
            'farms' => ScaffoldData::farms(),
            'units' => array_keys(ScaffoldData::units()),
            'line' => $this->line(),
            'categories' => array_keys($this->categories()),
            'subcategories' => $timeline['subcategories'],
            'reference' => ScaffoldData::nextReference(),
            'issuedOn' => $issuedOn,
            'responseDue' => $issuedOn->addDays($timeline['response']),
            'responseDays' => $timeline['response'],
            'implementationDue' => $issuedOn->addDays($timeline['implementation']),
            'implementationDays' => $timeline['implementation'],
        ]);
    }

    private function resetCategory(): void
    {
        $this->category = array_key_first($this->categories());
        $this->updatedCategory();
    }

    private function line(): string
    {
        return ScaffoldData::units()[$this->unit] ?? 'TABLE EGG';
    }

    /**
     * @return array<string, array{response: int, implementation: int, subcategories: list<string>}>
     */
    private function categories(): array
    {
        return ScaffoldData::matrix()[$this->line()];
    }
}
