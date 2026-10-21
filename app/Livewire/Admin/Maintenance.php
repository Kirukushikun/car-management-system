<?php

namespace App\Livewire\Admin;

use App\Models\BusinessLine;
use App\Models\Category;
use App\Models\ComplaintType;
use App\Models\Farm;
use App\Models\IssuedToUnit;
use App\Models\Subcategory;
use App\Support\TextLimits;
use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Maintenance: the values offered in the CAR form — farms, Issued To units, categories,
 * sub-categories and types of complaint. The IT Admin adds and renames them, and deactivates the
 * ones no longer used: they stop being offered on new CARs, while CARs that carry them keep them.
 * A value can only be deleted while nothing uses it.
 */
#[Title('Maintenance')]
class Maintenance extends Component
{
    /**
     * Tab key => [singular, plural].
     *
     * @var array<string, array{0: string, 1: string}>
     */
    public const TABS = [
        'farms' => ['farm', 'Farms'],
        'units' => ['unit', 'Issued to'],
        'categories' => ['category', 'Categories'],
        'subcategories' => ['sub-category', 'Sub-categories'],
        'types' => ['type of complaint', 'Types of complaint'],
    ];

    #[Url]
    public string $tab = 'farms';

    /**
     * Business line filter for units, categories and sub-categories ('' = all).
     */
    #[Url]
    public string $line = '';

    public bool $showForm = false;

    public ?int $editingId = null;

    public string $name = '';

    public ?int $businessLineId = null;

    public ?int $categoryId = null;

    public int|string $responseDays = 3;

    public int|string $implementationDays = 10;

    public string $description = '';

    public ?string $notice = null;

    public function mount(): void
    {
        $this->authorize('administer');

        if (! array_key_exists($this->tab, self::TABS)) {
            $this->tab = 'farms';
        }
    }

    public function updatedTab(): void
    {
        if (! array_key_exists($this->tab, self::TABS)) {
            $this->tab = 'farms';
        }

        $this->cancel();
        $this->notice = null;
    }

    public function updatedBusinessLineId(): void
    {
        $this->categoryId = null;
    }

    public function create(): void
    {
        $this->authorize('administer');

        $this->cancel();
        $this->businessLineId = $this->line !== '' ? (int) $this->line : BusinessLine::orderBy('id')->value('id');
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        $this->authorize('administer');

        $record = $this->query()->findOrFail($id);

        $this->cancel();
        $this->editingId = $record->id;
        $this->name = $record->name;
        $this->showForm = true;

        match (true) {
            $record instanceof IssuedToUnit => $this->businessLineId = $record->business_line_id,
            $record instanceof Category => [$this->businessLineId, $this->responseDays, $this->implementationDays] = [$record->business_line_id, $record->response_days, $record->implementation_days],
            $record instanceof Subcategory => [$this->businessLineId, $this->categoryId, $this->description] = [$record->category->business_line_id, $record->category_id, (string) $record->description],
            default => null,
        };
    }

    public function save(): void
    {
        $this->authorize('administer');

        $record = $this->editingId ? $this->query()->findOrFail($this->editingId) : $this->newRecord();
        $locked = $record->exists && $this->usage($record) > 0;

        $this->validate($this->rulesFor($record, $locked));

        $record->fill(['name' => trim($this->name)]);

        match (true) {
            $record instanceof IssuedToUnit => $record->fill($locked ? [] : ['business_line_id' => $this->businessLineId]),
            $record instanceof Category => $record->fill([
                ...($locked ? [] : ['business_line_id' => $this->businessLineId]),
                'response_days' => (int) $this->responseDays,
                'implementation_days' => (int) $this->implementationDays,
            ]),
            $record instanceof Subcategory => $record->fill([
                ...($locked ? [] : ['category_id' => $this->categoryId]),
                'description' => trim($this->description) ?: null,
            ]),
            default => null,
        };

        $label = self::TABS[$this->tab][0];
        $isNew = ! $record->exists;
        $record->save();

        $this->notice = $isNew
            ? "Added {$label} \"{$record->name}\". It is offered on new CARs now."
            : "Saved {$label} \"{$record->name}\".";
        $this->cancel();
    }

    public function cancel(): void
    {
        $this->reset('showForm', 'editingId', 'name', 'businessLineId', 'categoryId', 'responseDays', 'implementationDays', 'description');
        $this->resetErrorBag();
    }

    /**
     * Deactivated values are no longer offered on new CARs; CARs that carry them keep them.
     */
    public function toggleActive(int $id): void
    {
        $this->authorize('administer');

        $record = $this->query()->findOrFail($id);
        $record->update(['is_active' => ! $record->is_active]);

        $label = self::TABS[$this->tab][0];
        $this->notice = $record->is_active
            ? "Reactivated {$label} \"{$record->name}\". It is offered on new CARs again."
            : "Deactivated {$label} \"{$record->name}\". It is no longer offered on new CARs; CARs that already have it keep it.";

        if (! $record->is_active && $record instanceof Farm && ($users = $record->users()->count()) > 0) {
            $this->notice .= " {$users} ".str('user')->plural($users).' still '.($users === 1 ? 'belongs' : 'belong').' to this farm — move them in Users & Roles if needed.';
        }
    }

    /**
     * Only a value nothing uses can be deleted (a typo, say). Anything in use is deactivated instead.
     */
    public function delete(int $id): void
    {
        $this->authorize('administer');

        $record = $this->query()->findOrFail($id);
        $label = self::TABS[$this->tab][0];

        if ($this->usage($record) > 0) {
            $this->notice = "\"{$record->name}\" is in use, so it cannot be deleted. Deactivate it instead.";

            return;
        }

        $record->delete();
        $this->notice = "Deleted {$label} \"{$record->name}\".";
    }

    public function render(): View
    {
        $records = $this->query()
            ->when(in_array($this->tab, ['units', 'categories'], true) && $this->line !== '', fn (Builder $query) => $query->where('business_line_id', (int) $this->line))
            ->when($this->tab === 'subcategories' && $this->line !== '', fn (Builder $query) => $query->whereRelation('category', 'business_line_id', (int) $this->line))
            ->get();

        $editing = $this->editingId ? $this->query()->find($this->editingId) : null;

        return view('livewire.admin.maintenance', [
            'tabs' => self::TABS,
            'records' => $records,
            'usage' => $this->usageFor($records),
            'lines' => BusinessLine::orderBy('id')->get(),
            'categoryOptions' => Category::where('business_line_id', $this->businessLineId)->orderBy('id')->get(),
            'editing' => $editing,
            'locked' => $editing !== null && $this->usage($editing) > 0,
        ]);
    }

    /**
     * The tab's records, active first, in a stable order.
     *
     * @return Builder<Model>
     */
    private function query(): Builder
    {
        return match ($this->tab) {
            'units' => IssuedToUnit::with('businessLine')->orderByDesc('is_active')->orderBy('business_line_id')->orderBy('id'),
            'categories' => Category::with('businessLine')->withCount('subcategories')->orderByDesc('is_active')->orderBy('business_line_id')->orderBy('id'),
            'subcategories' => Subcategory::with('category.businessLine')
                ->select('subcategories.*')
                ->join('categories', 'categories.id', '=', 'subcategories.category_id')
                ->orderByDesc('subcategories.is_active')->orderBy('categories.business_line_id')->orderBy('subcategories.category_id')->orderBy('subcategories.id'),
            'types' => ComplaintType::query()->orderByDesc('is_active')->orderBy('id'),
            default => Farm::query()->orderByDesc('is_active')->orderBy('id'),
        };
    }

    private function newRecord(): Model
    {
        return match ($this->tab) {
            'units' => new IssuedToUnit,
            'categories' => new Category,
            'subcategories' => new Subcategory,
            'types' => new ComplaintType,
            default => new Farm,
        };
    }

    /**
     * How many CARs (and, for farms, users) use the record — and, for a category, how many
     * sub-categories it holds.
     */
    private function usage(Model $record): int
    {
        return $this->usageFor(new Collection([$record]))[$record->id]['total'];
    }

    /**
     * @param  Collection<int, Model>  $records
     * @return array<int, array{cars: int, users: int, subcategories: int, total: int}>
     */
    private function usageFor(Collection $records): array
    {
        $ids = $records->modelKeys();

        $cars = match ($this->tab) {
            'units' => DB::table('car_issued_to_unit')->whereIn('issued_to_unit_id', $ids)->groupBy('issued_to_unit_id')->selectRaw('issued_to_unit_id as id, count(*) as total')->pluck('total', 'id'),
            'categories' => DB::table('car_subcategory')->join('subcategories', 'subcategories.id', '=', 'car_subcategory.subcategory_id')
                ->whereIn('subcategories.category_id', $ids)->groupBy('subcategories.category_id')
                ->selectRaw('subcategories.category_id as id, count(distinct car_subcategory.car_id) as total')->pluck('total', 'id'),
            'subcategories' => DB::table('car_subcategory')->whereIn('subcategory_id', $ids)->groupBy('subcategory_id')->selectRaw('subcategory_id as id, count(*) as total')->pluck('total', 'id'),
            'types' => DB::table('car_complaint_type')->whereIn('complaint_type_id', $ids)->groupBy('complaint_type_id')->selectRaw('complaint_type_id as id, count(*) as total')->pluck('total', 'id'),
            default => DB::table('cars')->whereIn('farm_id', $ids)->groupBy('farm_id')->selectRaw('farm_id as id, count(*) as total')->pluck('total', 'id'),
        };
        $users = $this->tab === 'farms'
            ? DB::table('users')->whereIn('farm_id', $ids)->groupBy('farm_id')->selectRaw('farm_id as id, count(*) as total')->pluck('total', 'id')
            : collect();
        $subcategories = $this->tab === 'categories'
            ? DB::table('subcategories')->whereIn('category_id', $ids)->groupBy('category_id')->selectRaw('category_id as id, count(*) as total')->pluck('total', 'id')
            : collect();

        return $records->mapWithKeys(fn (Model $record): array => [$record->id => [
            'cars' => $count = (int) ($cars[$record->id] ?? 0),
            'users' => $userCount = (int) ($users[$record->id] ?? 0),
            'subcategories' => $subcategoryCount = (int) ($subcategories[$record->id] ?? 0),
            'total' => $count + $userCount + $subcategoryCount,
        ]])->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function rulesFor(Model $record, bool $locked): array
    {
        $unique = match (true) {
            $record instanceof IssuedToUnit => Rule::unique('issued_to_units', 'name'),
            $record instanceof Category => Rule::unique('categories', 'name')->where('business_line_id', $locked ? $record->business_line_id : $this->businessLineId),
            $record instanceof Subcategory => Rule::unique('subcategories', 'name')->where('category_id', $locked ? $record->category_id : $this->categoryId),
            $record instanceof ComplaintType => Rule::unique('complaint_types', 'name'),
            default => Rule::unique('farms', 'name'),
        };

        $rules = ['name' => ['required', 'string', 'max:'.TextLimits::SHORT, $unique->ignore($record->id)]];

        if (! $locked && ($record instanceof IssuedToUnit || $record instanceof Category || $record instanceof Subcategory)) {
            $rules['businessLineId'] = ['required', Rule::exists('business_lines', 'id')];
        }

        if ($record instanceof Category) {
            $rules['responseDays'] = ['required', 'integer', 'min:1', 'max:60'];
            $rules['implementationDays'] = ['required', 'integer', 'min:1', 'max:90', function (string $attribute, mixed $value, Closure $fail): void {
                if ((int) $value < (int) $this->responseDays) {
                    $fail('Implementation days cannot be fewer than response days.');
                }
            }];
        }

        if ($record instanceof Subcategory) {
            $rules['description'] = ['nullable', 'string', 'max:'.TextLimits::LONG];

            if (! $locked) {
                $rules['categoryId'] = ['required', Rule::exists('categories', 'id')->where('business_line_id', $this->businessLineId)];
            }
        }

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    protected function messages(): array
    {
        return [
            'name.unique' => 'That name is already used'.match ($this->tab) {
                'categories' => ' in this business line.',
                'subcategories' => ' in this category.',
                default => '.',
            },
            'categoryId.required' => 'Pick the category this sub-category belongs to.',
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function validationAttributes(): array
    {
        return [
            'businessLineId' => 'business line',
            'categoryId' => 'category',
            'responseDays' => 'response days',
            'implementationDays' => 'implementation days',
        ];
    }
}
