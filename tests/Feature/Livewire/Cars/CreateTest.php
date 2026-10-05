<?php

use App\Enums\Role;
use App\Livewire\Cars\Create;
use App\Models\Category;
use App\Models\IssuedToUnit;
use App\Models\Subcategory;
use App\Models\User;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

beforeEach(function () {
    Carbon::setTestNow('2026-10-02');
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

it('prefills the issuer and the first unit, category and sub-category', function () {
    Livewire::actingAs($this->requestor)
        ->test(Create::class)
        ->assertSet('issuedBy', 'Gab Maglalang — Requestor')
        ->assertSet('unitId', unitId('Eggroom'))
        ->assertSet('categoryId', categoryId('TABLE EGG', 'Production Related'))
        ->assertSet('subcategoryId', subcategoryId('TABLE EGG', 'Production Related', 'Shell Quality Defects'))
        ->assertSee('Business line: TABLE EGG');
});

it('switches to the DOP categories when a DOP unit is chosen', function () {
    Livewire::actingAs($this->requestor)
        ->test(Create::class)
        ->set('unitId', unitId('Hatchery'))
        ->assertSee('Business line: DOP')
        ->assertSet('categoryId', categoryId('DOP', 'Production Related'))
        ->assertSet('subcategoryId', subcategoryId('DOP', 'Production Related', 'Hatchery Production & Hatch Rates'))
        ->assertSee('Sales & Order Management');
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

it('previews deadlines from the issued date and the category timeline', function () {
    Livewire::actingAs($this->requestor)
        ->test(Create::class)
        ->assertSee('Oct 5, 2026 (+3d)')
        ->assertSee('Oct 12, 2026 (+10d)')
        ->set('unitId', unitId('Hatchery'))
        ->set('categoryId', categoryId('DOP', 'Compliance & Standards'))
        ->assertSee('Oct 3, 2026 (+1d)')
        ->assertSee('Oct 5, 2026 (+3d)');
});

it('uses the timelines currently in the matrix', function () {
    Category::whereKey(categoryId('TABLE EGG', 'Production Related'))->update(['response_days' => 2, 'implementation_days' => 7]);

    Livewire::actingAs($this->requestor)
        ->test(Create::class)
        ->assertSee('Oct 4, 2026 (+2d)')
        ->assertSee('Oct 9, 2026 (+7d)');
});

it('requires a complainant and problem details before submitting', function () {
    Livewire::actingAs($this->requestor)
        ->test(Create::class)
        ->call('submit')
        ->assertHasErrors(['complainant' => 'required', 'problem' => 'required']);
});

it('rejects a sub-category from a different category', function () {
    Livewire::actingAs($this->requestor)
        ->test(Create::class)
        ->set('complainant', 'Rollie Funa')
        ->set('problem', '34 trays of big dirty eggs returned as spoiled.')
        ->set('subcategoryId', subcategoryId('DOP', 'Sales & Order Management', 'Booking & Sales Cancellations'))
        ->call('submit')
        ->assertHasErrors(['subcategoryId' => 'exists'])
        ->assertSee('Pick a sub-category that belongs to the selected category.');
});

it('accepts a valid form and explains that saving is still a stub', function () {
    Livewire::actingAs($this->requestor)
        ->test(Create::class)
        ->set('complainant', 'Rollie Funa')
        ->set('problem', '34 trays of big dirty eggs returned as spoiled.')
        ->call('submit')
        ->assertHasNoErrors()
        ->assertSee('wired up in Phase 3');
});
