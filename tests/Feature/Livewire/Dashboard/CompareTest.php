<?php

use App\Enums\Role;
use App\Livewire\Dashboard\Compare;
use App\Livewire\Dashboard\Index;
use App\Models\Car;
use App\Models\Farm;
use App\Models\User;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

beforeEach(function () {
    Carbon::setTestNow('2026-10-21 09:00:00');
    $this->monitor = User::factory()->role(Role::Monitor)->create();
});

it('is opened from the dashboard by the QA Monitor', function () {
    Livewire::actingAs($this->monitor)
        ->test(Index::class)
        ->assertSee('Compare periods')
        ->assertSeeHtml('href="'.route('dashboard.compare').'"');

    $this->actingAs($this->monitor)->get(route('dashboard.compare'))->assertOk()->assertSeeLivewire(Compare::class);
});

it('is for the QA Monitor only', function (Role $role) {
    $user = User::factory()->role($role)->create();

    $this->actingAs($user)->get(route('dashboard.compare'))->assertForbidden();
    $this->actingAs($user)->get(route('dashboard.compare.export'))->assertForbidden();
})->with([Role::Requestor, Role::RequestorApprover, Role::Responder, Role::ResponderApprover, Role::Admin]);

it('starts on last year against this year, cut to the same span, and shows every section', function () {
    Car::factory()->create(['issued_on' => '2025-03-01']);
    Car::factory()->count(2)->create(['issued_on' => '2026-03-01']);

    Livewire::actingAs($this->monitor)
        ->test(Compare::class)
        ->assertSet('leftYear', 2025)
        ->assertSet('rightYear', 2026)
        ->assertSee(['Highlights', 'CARs issued up 100% (1 → 2).', 'CARs issued', 'On-time response', 'CARs issued per month', 'By sub-category', 'By category', 'By unit', 'By farm'])
        ->assertSee('both sides were cut to the same span: Jan 1, 2025 – Oct 21, 2025 and Jan 1, 2026 – Oct 21, 2026.');
});

it('changes a side to a quarter and keeps the choices in the URL', function () {
    Livewire::withQueryParams(['leftYear' => 2025, 'leftPeriod' => 'q1'])
        ->actingAs($this->monitor)
        ->test(Compare::class)
        ->assertSet('leftPeriod', 'q1')
        ->set('rightPeriod', 'q1')
        ->assertSee(['Q1 2025', 'Q1 2026', 'Jan 1, 2026 – Mar 31, 2026']);
});

it('makes the right side follow the left filters until that is switched off', function () {
    $pfc = Farm::firstOrCreate(['name' => 'PFC']);
    $hatchery = Farm::firstOrCreate(['name' => 'HATCHERY']);

    Livewire::actingAs($this->monitor)
        ->test(Compare::class)
        ->set('leftFarm', $pfc->id)
        ->assertSet('rightFarm', $pfc->id)
        ->set('sameFilters', false)
        ->set('rightFarm', $hatchery->id)
        ->assertSet('rightFarm', $hatchery->id)
        ->assertSet('leftFarm', $pfc->id);
});

it('has print, export and a guide', function () {
    Livewire::actingAs($this->monitor)
        ->test(Compare::class)
        ->assertSeeHtml('window.print()')
        ->assertSeeHtml('class="print-only compare-print-head"')
        ->assertSee(['Export CSV', 'Period comparison guide', 'Reading the comparison', 'Green is an improvement']);
});

it('exports the same comparison as a CSV', function () {
    Car::factory()->count(2)->create(['issued_on' => '2026-02-01']);

    $response = $this->actingAs($this->monitor)->get(route('dashboard.compare.export', [
        'leftYear' => 2025, 'leftPeriod' => 'q1', 'rightYear' => 2026, 'rightPeriod' => 'q1',
    ]));

    $response->assertOk()->assertDownload('car-comparison-q1-2025-vs-q1-2026.csv');
    $csv = $response->streamedContent();

    expect($csv)->toContain('Section,Item,"Q1 2025","Q1 2026",Change')
        ->toContain('Headline,"CARs issued",0,2,')
        ->toContain('"By farm",PFC,0,2,');
});

it('clears back to last year against this year, full years, all lines and farms', function () {
    $pfc = Farm::firstOrCreate(['name' => 'PFC']);

    Livewire::actingAs($this->monitor)
        ->test(Compare::class)
        ->set('leftYear', 2023)
        ->set('leftPeriod', 'q2')
        ->set('rightPeriod', 'custom')
        ->set('rightFrom', '2026-02-01')
        ->set('leftFarm', $pfc->id)
        ->set('sameSpan', false)
        ->call('clear')
        ->assertSet('leftYear', 2025)
        ->assertSet('rightYear', 2026)
        ->assertSet('leftPeriod', 'year')
        ->assertSet('rightPeriod', 'year')
        ->assertSet('rightFrom', null)
        ->assertSet('leftFarm', null)
        ->assertSet('rightFarm', null)
        ->assertSet('leftLine', null)
        ->assertSet('sameFilters', true)
        ->assertSet('sameSpan', true)
        ->assertSet('linkYears', true);
});

it('moves the other year with the one picked while consecutive years is ticked', function () {
    Livewire::actingAs($this->monitor)
        ->test(Compare::class)
        ->assertSet('linkYears', true)
        ->set('leftYear', 2025)
        ->assertSet('rightYear', 2026)
        ->set('rightYear', 2024)
        ->assertSet('leftYear', 2023)
        ->set('leftYear', 2021)
        ->assertSet('rightYear', 2022)
        ->assertSet('linkYears', true);
});

it('lets any two years be picked once consecutive years is unticked, and lines them up again when ticked', function () {
    Livewire::actingAs($this->monitor)
        ->test(Compare::class)
        ->set('linkYears', false)
        ->set('leftYear', 2022)
        ->set('rightYear', 2026)
        ->assertSet('leftYear', 2022)
        ->assertSet('rightYear', 2026)
        ->set('linkYears', true)
        ->assertSet('leftYear', 2025)
        ->assertSet('rightYear', 2026);
});

it('opens a saved link on its years', function () {
    Livewire::withQueryParams(['leftYear' => 2020, 'rightYear' => 2021])
        ->actingAs($this->monitor)
        ->test(Compare::class)
        ->assertSet('leftYear', 2020)
        ->assertSet('rightYear', 2021);

    Livewire::withQueryParams(['leftYear' => 2019, 'rightYear' => 2024, 'linkYears' => 0])
        ->actingAs($this->monitor)
        ->test(Compare::class)
        ->assertSet('leftYear', 2019)
        ->assertSet('rightYear', 2024);
});
