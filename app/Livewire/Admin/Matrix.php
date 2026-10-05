<?php

namespace App\Livewire\Admin;

use App\Models\BusinessLine;
use App\Models\Category;
use Closure;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Category matrix: response and CA implementation timelines (days) per category.
 * Edits apply to CARs issued afterwards — issued CARs keep their snapshotted deadlines.
 */
#[Title('Category Matrix')]
class Matrix extends Component
{
    /**
     * Day values being edited, keyed by category id.
     *
     * @var array<int, array{response: int|string, implementation: int|string}>
     */
    public array $days = [];

    public ?string $notice = null;

    public function mount(): void
    {
        $this->authorize('administer');
        $this->loadDays();
    }

    public function save(): void
    {
        $this->authorize('administer');

        $this->validate();

        $changed = 0;

        foreach (Category::whereKey(array_keys($this->days))->get() as $category) {
            $category->fill([
                'response_days' => (int) $this->days[$category->id]['response'],
                'implementation_days' => (int) $this->days[$category->id]['implementation'],
            ]);

            if ($category->isDirty()) {
                $category->save();
                $changed++;
            }
        }

        $this->loadDays();
        $this->notice = $changed === 0
            ? 'No changes to save.'
            : "Updated {$changed} ".str('category')->plural($changed).'. New CARs use the new timelines; CARs already issued keep their deadlines.';
    }

    public function discard(): void
    {
        $this->loadDays();
        $this->resetErrorBag();
        $this->notice = null;
    }

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        return [
            'days' => ['array'],
            'days.*.response' => ['required', 'integer', 'min:1', 'max:60'],
            'days.*.implementation' => ['required', 'integer', 'min:1', 'max:90', function (string $attribute, mixed $value, Closure $fail): void {
                $categoryId = (int) explode('.', $attribute)[1];

                if ((int) $value < (int) ($this->days[$categoryId]['response'] ?? 0)) {
                    $fail('Implementation days cannot be fewer than response days.');
                }
            }],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function validationAttributes(): array
    {
        return [
            'days.*.response' => 'response days',
            'days.*.implementation' => 'implementation days',
        ];
    }

    public function render(): View
    {
        return view('livewire.admin.matrix', [
            'lines' => BusinessLine::with(['categories' => fn ($query) => $query->orderBy('id')->with(['subcategories' => fn ($query) => $query->orderBy('id')])])
                ->orderBy('id')
                ->get(),
        ]);
    }

    private function loadDays(): void
    {
        $this->days = Category::orderBy('id')->get()
            ->mapWithKeys(fn (Category $category): array => [
                $category->id => ['response' => $category->response_days, 'implementation' => $category->implementation_days],
            ])
            ->all();
    }
}
