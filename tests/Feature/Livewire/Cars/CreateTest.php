<?php

use App\Enums\Role;
use App\Livewire\Cars\Create;
use App\Models\User;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

beforeEach(function () {
    Carbon::setTestNow('2026-10-02');
    $this->requestor = User::factory()->role(Role::Requestor)->create(['name' => 'Gab Maglalang']);
});

it('prefills the issuer and the first unit, category and sub-category', function () {
    Livewire::actingAs($this->requestor)
        ->test(Create::class)
        ->assertSet('issuedBy', 'Gab Maglalang — Requestor')
        ->assertSet('unit', 'Eggroom')
        ->assertSet('category', 'Production Related')
        ->assertSet('subcategory', 'Shell Quality Defects')
        ->assertSee('Business line: TABLE EGG');
});

it('switches to the DOP categories when a DOP unit is chosen', function () {
    Livewire::actingAs($this->requestor)
        ->test(Create::class)
        ->set('unit', 'Hatchery')
        ->assertSee('Business line: DOP')
        ->assertSet('category', 'Production Related')
        ->assertSet('subcategory', 'Hatchery Production & Hatch Rates')
        ->assertSee('Sales & Order Management');
});

it('resets the sub-category when the category changes', function () {
    Livewire::actingAs($this->requestor)
        ->test(Create::class)
        ->set('category', 'Logistics & Distribution Transport')
        ->assertSet('subcategory', 'Issuance & Delivery Errors');
});

it('previews deadlines from the issued date and the category timeline', function () {
    Livewire::actingAs($this->requestor)
        ->test(Create::class)
        ->assertSee('Oct 5, 2026 (+3d)')
        ->assertSee('Oct 12, 2026 (+10d)')
        ->set('unit', 'Hatchery')
        ->set('category', 'Compliance & Standards')
        ->assertSee('Oct 3, 2026 (+1d)')
        ->assertSee('Oct 5, 2026 (+3d)');
});

it('requires a complainant and problem details before submitting', function () {
    Livewire::actingAs($this->requestor)
        ->test(Create::class)
        ->call('submit')
        ->assertHasErrors(['complainant' => 'required', 'problem' => 'required']);
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
