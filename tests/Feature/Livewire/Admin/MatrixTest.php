<?php

use App\Enums\Role;
use App\Livewire\Admin\Matrix;
use App\Models\Category;
use App\Models\User;
use Database\Seeders\ReferenceDataSeeder;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(ReferenceDataSeeder::class);
    $this->admin = User::factory()->role(Role::Admin)->create();
    $this->category = Category::whereRelation('businessLine', 'name', 'TABLE EGG')->where('name', 'Production Related')->sole();
});

it('lists every category with its current days', function () {
    Livewire::actingAs($this->admin)
        ->test(Matrix::class)
        ->assertSee('TABLE EGG')
        ->assertSee('Sales & Order Management')
        ->assertSet("days.{$this->category->id}.response", 3)
        ->assertSet("days.{$this->category->id}.implementation", 10);
});

it('saves changed timelines and reports how many categories changed', function () {
    Livewire::actingAs($this->admin)
        ->test(Matrix::class)
        ->set("days.{$this->category->id}.response", 2)
        ->set("days.{$this->category->id}.implementation", 7)
        ->call('save')
        ->assertHasNoErrors()
        ->assertSee('Updated 1 category.');

    expect($this->category->fresh())
        ->response_days->toBe(2)
        ->implementation_days->toBe(7);
});

it('reports when nothing changed', function () {
    Livewire::actingAs($this->admin)
        ->test(Matrix::class)
        ->call('save')
        ->assertSee('No changes to save.');
});

it('rejects implementation days shorter than response days', function () {
    Livewire::actingAs($this->admin)
        ->test(Matrix::class)
        ->set("days.{$this->category->id}.response", 5)
        ->set("days.{$this->category->id}.implementation", 4)
        ->call('save')
        ->assertHasErrors("days.{$this->category->id}.implementation")
        ->assertSee('Implementation days cannot be fewer than response days.');

    expect($this->category->fresh()->response_days)->toBe(3);
});

it('rejects zero and blank day values', function (mixed $value) {
    Livewire::actingAs($this->admin)
        ->test(Matrix::class)
        ->set("days.{$this->category->id}.response", $value)
        ->call('save')
        ->assertHasErrors("days.{$this->category->id}.response");
})->with([0, '']);

it('discards unsaved edits', function () {
    Livewire::actingAs($this->admin)
        ->test(Matrix::class)
        ->set("days.{$this->category->id}.response", 9)
        ->call('discard')
        ->assertSet("days.{$this->category->id}.response", 3);
});

it('forbids saving for anyone but the IT Admin', function () {
    $component = Livewire::actingAs($this->admin)->test(Matrix::class);
    $this->admin->update(['role' => Role::Monitor]);

    $component->set("days.{$this->category->id}.response", 2)->call('save')->assertForbidden();

    expect($this->category->fresh()->response_days)->toBe(3);
});
