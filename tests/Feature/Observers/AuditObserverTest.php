<?php

use App\Enums\Role;
use App\Livewire\Admin\AuditLog;
use App\Livewire\Admin\Matrix;
use App\Livewire\Admin\Users;
use App\Models\Audit;
use App\Models\Category;
use App\Models\User;
use Database\Seeders\ReferenceDataSeeder;
use Livewire\Livewire;

beforeEach(function () {
    $this->admin = User::factory()->role(Role::Admin)->create();
    Audit::query()->delete();
});

it('records who changed a user, from what to what', function () {
    $user = User::factory()->create(['name' => 'Old Name']);

    Livewire::actingAs($this->admin)
        ->test(Users::class)
        ->call('edit', $user->id)
        ->set('form.name', 'New Name')
        ->call('save');

    $audit = Audit::where('auditable_type', 'user')->where('auditable_id', $user->id)->latest('id')->first();

    expect($audit)
        ->user_id->toBe($this->admin->id)
        ->event->toBe('updated')
        ->old_values->toBe(['name' => 'Old Name'])
        ->new_values->toBe(['name' => 'New Name']);
});

it('never stores a password in the audit log', function () {
    $user = User::factory()->create();

    $user->update(['password' => 'some-new-secret']);

    expect(Audit::where('auditable_id', $user->id)->latest('id')->first())
        ->old_values->toBe(['password' => '(changed)'])
        ->new_values->toBe(['password' => '(changed)']);
});

it('records matrix day changes', function () {
    $this->seed(ReferenceDataSeeder::class);
    $category = Category::firstOrFail();

    Livewire::actingAs($this->admin)
        ->test(Matrix::class)
        ->set("days.{$category->id}.response", 2)
        ->call('save');

    expect(Audit::where('auditable_type', 'category')->sole())
        ->old_values->toBe(['response_days' => 3])
        ->new_values->toBe(['response_days' => 2]);
});

it('keeps the audit log append-only', function () {
    $audit = Audit::create(['auditable_type' => 'user', 'auditable_id' => 1, 'event' => 'updated']);

    expect(fn () => $audit->update(['event' => 'created']))->toThrow(LogicException::class)
        ->and(fn () => $audit->delete())->toThrow(LogicException::class);
});

it('shows the audit log to the IT Admin only', function () {
    User::factory()->create(['name' => 'Audited Person']);

    Livewire::actingAs($this->admin)->test(AuditLog::class)->assertSee(['Audited Person', 'created']);

    $this->actingAs(User::factory()->role(Role::Monitor)->create())->get(route('admin.audit'))->assertForbidden();
});
