<?php

use App\Enums\Role;
use App\Livewire\Admin\Maintenance;
use App\Models\Audit;
use App\Models\BusinessLine;
use App\Models\Car;
use App\Models\Category;
use App\Models\ComplaintType;
use App\Models\Farm;
use App\Models\IssuedToUnit;
use App\Models\Subcategory;
use App\Models\User;
use Database\Seeders\ReferenceDataSeeder;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(ReferenceDataSeeder::class);
    $this->admin = User::factory()->role(Role::Admin)->create();
    $this->tableEgg = BusinessLine::where('name', 'TABLE EGG')->value('id');
});

it('is for the IT Admin only, and sits in their sidebar', function () {
    $this->actingAs(User::factory()->role(Role::Monitor)->create())->get(route('admin.maintenance'))->assertForbidden();
    $this->actingAs($this->admin)->get(route('admin.maintenance'))->assertOk()->assertSee(['Farms', 'Issued to', 'Categories', 'Sub-categories', 'Types of complaint']);

    expect(collect(Role::Admin->navigation())->pluck('route'))->toContain('admin.maintenance');
});

it('lists each tab with how much it is used', function () {
    $car = Car::factory()->create(['farm_id' => Farm::where('name', 'PFC')->value('id')]);

    Livewire::actingAs($this->admin)
        ->test(Maintenance::class)
        ->assertSee(['PFC', 'HATCHERY', '1 CAR', 'Not used'])
        ->set('tab', 'types')
        ->assertSee(['Product Nonconformity', 'Service Nonconformity', 'Others'])
        ->set('tab', 'categories')
        ->assertSee(['Production Related', '3 sub-categories', '3 days', '10 days'])
        ->set('line', (string) BusinessLine::where('name', 'DOP')->value('id'))
        ->assertSee('Sales &amp; Order Management', false)
        ->assertDontSee('Logistics &amp; Distribution Transport', false);

    expect($car->complaintTypeLabels())->toBe('Product Nonconformity');
});

it('adds a value to each list', function (string $tab, array $fields, string $model, array $expected) {
    $component = Livewire::actingAs($this->admin)->test(Maintenance::class)->set('tab', $tab)->call('create');

    foreach ($fields as $field => $value) {
        $component->set($field, $value instanceof Closure ? $value() : $value);
    }

    $component->call('save')->assertHasNoErrors()->assertSee('It is offered on new CARs now.');

    expect($model::where('name', $fields['name'])->sole())->toMatchArray($expected)->is_active->toBeTrue();
})->with([
    'farm' => ['farms', ['name' => 'SAN JOSE'], Farm::class, []],
    'unit' => ['units', ['name' => 'Feedmill'], IssuedToUnit::class, []],
    'category' => ['categories', ['name' => 'Feed Quality', 'responseDays' => 2, 'implementationDays' => 7], Category::class, ['response_days' => 2, 'implementation_days' => 7]],
    'sub-category' => ['subcategories', ['name' => 'Pest Sightings', 'categoryId' => fn () => Category::where('name', 'Compliance & Standards')->orderBy('id')->value('id'), 'description' => 'Rats, flies, birds.'], Subcategory::class, ['description' => 'Rats, flies, birds.']],
    'complaint type' => ['types', ['name' => 'Documentation Nonconformity'], ComplaintType::class, []],
]);

it('renames a value, and refuses a duplicate name', function () {
    $farm = Farm::where('name', 'BFC')->sole();

    Livewire::actingAs($this->admin)
        ->test(Maintenance::class)
        ->call('edit', $farm->id)
        ->assertSet('name', 'BFC')
        ->set('name', 'PFC')
        ->call('save')
        ->assertHasErrors(['name' => 'unique'])
        ->set('name', 'BFC MAIN')
        ->call('save')
        ->assertHasNoErrors()
        ->assertSee('Saved farm "BFC MAIN".');

    expect($farm->fresh()->name)->toBe('BFC MAIN');
});

it('allows the same category name in another business line, but not twice in one', function () {
    Livewire::actingAs($this->admin)
        ->test(Maintenance::class)
        ->set('tab', 'categories')
        ->call('create')
        ->set('businessLineId', $this->tableEgg)
        ->set('name', 'Sales & Order Management')
        ->call('save')
        ->assertHasNoErrors()
        ->call('create')
        ->set('businessLineId', $this->tableEgg)
        ->set('name', 'Production Related')
        ->call('save')
        ->assertHasErrors(['name' => 'unique']);
});

it('keeps implementation days at or above response days', function () {
    Livewire::actingAs($this->admin)
        ->test(Maintenance::class)
        ->set('tab', 'categories')
        ->call('create')
        ->set('name', 'Feed Quality')
        ->set('responseDays', 5)
        ->set('implementationDays', 3)
        ->call('save')
        ->assertHasErrors(['implementationDays']);
});

it('deactivates and reactivates a value', function () {
    $type = ComplaintType::where('name', 'Others')->sole();

    Livewire::actingAs($this->admin)
        ->test(Maintenance::class)
        ->set('tab', 'types')
        ->call('toggleActive', $type->id)
        ->assertSee('It is no longer offered on new CARs')
        ->assertSee('Inactive');

    expect($type->fresh()->is_active)->toBeFalse();

    Livewire::actingAs($this->admin)->test(Maintenance::class)->set('tab', 'types')->call('toggleActive', $type->id);

    expect($type->fresh()->is_active)->toBeTrue();
});

it('warns when a deactivated farm still has users', function () {
    $farm = Farm::where('name', 'HATCHERY')->sole();
    User::factory()->role(Role::Responder, 'HATCHERY')->create();

    Livewire::actingAs($this->admin)
        ->test(Maintenance::class)
        ->call('toggleActive', $farm->id)
        ->assertSee('1 user still belongs to this farm');
});

it('deletes only values nothing uses', function () {
    $car = Car::factory()->create();
    $usedType = $car->complaintTypes->first();
    $unusedType = ComplaintType::where('name', 'Others')->sole();

    Livewire::actingAs($this->admin)
        ->test(Maintenance::class)
        ->set('tab', 'types')
        ->call('delete', $usedType->id)
        ->assertSee('is in use, so it cannot be deleted')
        ->call('delete', $unusedType->id)
        ->assertSee('Deleted type of complaint "Others".');

    expect(ComplaintType::whereKey($usedType->id)->exists())->toBeTrue()
        ->and(ComplaintType::whereKey($unusedType->id)->exists())->toBeFalse();
});

it('does not delete a category that still has sub-categories', function () {
    $category = Category::where('name', 'Production Related')->orderBy('id')->first();

    Livewire::actingAs($this->admin)
        ->test(Maintenance::class)
        ->set('tab', 'categories')
        ->call('delete', $category->id)
        ->assertSee('is in use');

    expect($category->fresh())->not->toBeNull();
});

it('fixes the business line and category of values already used on CARs', function () {
    $car = Car::factory()->create();
    $subcategory = $car->subcategories->first();
    $otherCategory = Category::factory()->create(['business_line_id' => $subcategory->category->business_line_id]);

    Livewire::actingAs($this->admin)
        ->test(Maintenance::class)
        ->set('tab', 'subcategories')
        ->call('edit', $subcategory->id)
        ->assertSee('Fixed — it is already in use.')
        ->set('categoryId', $otherCategory->id)
        ->set('name', 'Renamed sub-category')
        ->call('save')
        ->assertHasNoErrors();

    expect($subcategory->fresh())->category_id->toBe($subcategory->category_id)->name->toBe('Renamed sub-category');
});

it('records adds, changes and deletes in the audit log', function () {
    Livewire::actingAs($this->admin)
        ->test(Maintenance::class)
        ->set('tab', 'units')
        ->call('create')
        ->set('name', 'Feedmill')
        ->call('save');

    $unit = IssuedToUnit::where('name', 'Feedmill')->sole();

    Livewire::actingAs($this->admin)->test(Maintenance::class)->set('tab', 'units')->call('toggleActive', $unit->id)->call('delete', $unit->id);

    expect(Audit::where('auditable_type', 'issued_to_unit')->where('auditable_id', $unit->id)->pluck('event')->all())->toBe(['created', 'updated', 'deleted']);

    $this->actingAs($this->admin)->get(route('admin.audit'))->assertSee(['Issued To Unit', 'Feedmill', 'deleted']);
});
