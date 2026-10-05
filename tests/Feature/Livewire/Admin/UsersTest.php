<?php

use App\Enums\Role;
use App\Livewire\Admin\Users;
use App\Models\Farm;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

beforeEach(function () {
    $this->admin = User::factory()->role(Role::Admin)->create();
});

describe('creating users', function () {
    it('creates a requestor with an approver and a hashed initial password', function () {
        $approver = User::factory()->role(Role::RequestorApprover)->create();

        Livewire::actingAs($this->admin)
            ->test(Users::class)
            ->call('create')
            ->set('form.name', 'Juan Dela Cruz')
            ->set('form.email', 'juan@car.test')
            ->set('form.role', Role::Requestor->value)
            ->set('form.approverId', $approver->id)
            ->set('form.password', 'secret-pass')
            ->call('save')
            ->assertHasNoErrors()
            ->assertSee('Created Juan Dela Cruz as Requestor.')
            ->assertSet('showForm', false);

        $user = User::where('email', 'juan@car.test')->sole();

        expect($user)
            ->role->toBe(Role::Requestor)
            ->farm_id->toBeNull()
            ->approver_id->toBe($approver->id)
            ->is_active->toBeTrue()
            ->and(Hash::check('secret-pass', $user->password))->toBeTrue();
    });

    it('requires a name, email and initial password', function () {
        Livewire::actingAs($this->admin)
            ->test(Users::class)
            ->call('create')
            ->call('save')
            ->assertHasErrors(['form.name' => 'required', 'form.email' => 'required', 'form.password' => 'required']);
    });

    it('rejects an email that is already used', function () {
        $existing = User::factory()->create();

        Livewire::actingAs($this->admin)
            ->test(Users::class)
            ->call('create')
            ->set('form.name', 'Someone')
            ->set('form.email', $existing->email)
            ->set('form.password', 'secret-pass')
            ->call('save')
            ->assertHasErrors(['form.email' => 'unique']);
    });

    it('requires a farm for responder roles', function () {
        Livewire::actingAs($this->admin)
            ->test(Users::class)
            ->call('create')
            ->set('form.name', 'New Responder')
            ->set('form.email', 'responder@car.test')
            ->set('form.role', Role::Responder->value)
            ->set('form.password', 'secret-pass')
            ->call('save')
            ->assertHasErrors(['form.farmId' => 'required'])
            ->assertSee('Responders and Responder Approvers must belong to a farm.');
    });

    it('treats the empty farm option as no farm', function () {
        Livewire::actingAs($this->admin)
            ->test(Users::class)
            ->call('create')
            ->set('form.role', Role::Responder->value)
            ->set('form.farmId', '')
            ->assertSet('form.farmId', null)
            ->set('form.name', 'New Responder')
            ->set('form.email', 'responder@car.test')
            ->set('form.password', 'secret-pass')
            ->call('save')
            ->assertHasErrors(['form.farmId' => 'required']);
    });

    it('drops the farm for roles that are not tied to a farm', function () {
        $farm = Farm::factory()->create();

        Livewire::actingAs($this->admin)
            ->test(Users::class)
            ->call('create')
            ->set('form.name', 'QA Person')
            ->set('form.email', 'qa@car.test')
            ->set('form.role', Role::Monitor->value)
            ->set('form.farmId', $farm->id)
            ->set('form.password', 'secret-pass')
            ->call('save')
            ->assertHasNoErrors();

        expect(User::where('email', 'qa@car.test')->value('farm_id'))->toBeNull();
    });
});

describe('approver chain', function () {
    function saveResponderWithApprover(User $admin, Farm $farm, User $approver): Testable
    {
        return Livewire::actingAs($admin)
            ->test(Users::class)
            ->call('create')
            ->set('form.name', 'New Responder')
            ->set('form.email', 'new.responder@car.test')
            ->set('form.role', Role::Responder->value)
            ->set('form.farmId', $farm->id)
            ->set('form.approverId', $approver->id)
            ->set('form.password', 'secret-pass')
            ->call('save');
    }

    it('accepts a responder approver on the same farm', function () {
        $approver = User::factory()->role(Role::ResponderApprover, 'PFC')->create();

        saveResponderWithApprover($this->admin, $approver->farm, $approver)->assertHasNoErrors();
    });

    it('rejects an approver with the wrong role', function () {
        $wrongRole = User::factory()->role(Role::RequestorApprover)->create();

        saveResponderWithApprover($this->admin, Farm::factory()->create(), $wrongRole)
            ->assertHasErrors('form.approverId')
            ->assertSee('The approver of a Responder must be a Responder Approver.');
    });

    it('rejects an approver from another farm', function () {
        $approver = User::factory()->role(Role::ResponderApprover, 'HATCHERY')->create();
        $pfc = Farm::firstOrCreate(['name' => 'PFC']);

        saveResponderWithApprover($this->admin, $pfc, $approver)
            ->assertHasErrors('form.approverId')
            ->assertSee('The approver must belong to the same farm.');
    });

    it('rejects a deactivated approver', function () {
        $approver = User::factory()->role(Role::ResponderApprover)->inactive()->create();

        saveResponderWithApprover($this->admin, $approver->farm, $approver)
            ->assertHasErrors(['form.approverId' => 'exists']);
    });

    it('rejects a user as their own approver', function () {
        $approver = User::factory()->role(Role::RequestorApprover)->create();

        Livewire::actingAs($this->admin)
            ->test(Users::class)
            ->call('edit', $approver->id)
            ->set('form.approverId', $approver->id)
            ->call('save')
            ->assertHasErrors('form.approverId')
            ->assertSee('A user cannot approve their own work.');
    });

    it('rejects an approver whose chain leads back to the user', function () {
        $senior = User::factory()->role(Role::RequestorApprover)->create();
        $junior = User::factory()->role(Role::RequestorApprover)->create(['approver_id' => $senior->id]);

        Livewire::actingAs($this->admin)
            ->test(Users::class)
            ->call('edit', $senior->id)
            ->set('form.approverId', $junior->id)
            ->call('save')
            ->assertHasErrors('form.approverId')
            ->assertSee("{$junior->name}'s approver chain already leads back to {$senior->name}.");

        expect($senior->fresh()->approver_id)->toBeNull();
    });

    it('lists only eligible approvers for the selected role and farm', function () {
        $pfcApprover = User::factory()->role(Role::ResponderApprover, 'PFC')->create(['name' => 'Pfc Approver']);
        User::factory()->role(Role::ResponderApprover, 'HATCHERY')->create(['name' => 'Hatchery Approver']);
        User::factory()->role(Role::RequestorApprover)->create(['name' => 'Sales Approver']);

        Livewire::actingAs($this->admin)
            ->test(Users::class)
            ->call('create')
            ->set('form.role', Role::Responder->value)
            ->set('form.farmId', $pfcApprover->farm_id)
            ->assertViewHas('approverOptions', fn ($options) => $options->pluck('name')->all() === ['Pfc Approver']);
    });
});

describe('editing users', function () {
    it('keeps the password when the field is left blank', function () {
        $user = User::factory()->create();
        $originalHash = $user->password;

        Livewire::actingAs($this->admin)
            ->test(Users::class)
            ->call('edit', $user->id)
            ->set('form.name', 'Renamed Person')
            ->call('save')
            ->assertHasNoErrors();

        expect($user->fresh())
            ->name->toBe('Renamed Person')
            ->password->toBe($originalHash);
    });

    it('resets the password when a new one is given', function () {
        $user = User::factory()->create();

        Livewire::actingAs($this->admin)
            ->test(Users::class)
            ->call('edit', $user->id)
            ->set('form.password', 'brand-new-pass')
            ->call('save')
            ->assertHasNoErrors();

        expect(Hash::check('brand-new-pass', $user->fresh()->password))->toBeTrue();
    });

    it('does not let the admin change their own role', function () {
        Livewire::actingAs($this->admin)
            ->test(Users::class)
            ->call('edit', $this->admin->id)
            ->set('form.role', Role::Monitor->value)
            ->call('save')
            ->assertHasErrors('form.role')
            ->assertSee('You cannot change your own role.');

        expect($this->admin->fresh()->role)->toBe(Role::Admin);
    });

    it('blocks a role change while other users report to this person', function () {
        $approver = User::factory()->role(Role::RequestorApprover)->create();
        User::factory()->role(Role::Requestor)->create(['approver_id' => $approver->id]);

        Livewire::actingAs($this->admin)
            ->test(Users::class)
            ->call('edit', $approver->id)
            ->set('form.role', Role::Monitor->value)
            ->call('save')
            ->assertHasErrors('form.role')
            ->assertSee('is the approver for 1 active user');

        expect($approver->fresh()->role)->toBe(Role::RequestorApprover);
    });
});

describe('deactivating users', function () {
    it('deactivates and reactivates a user', function () {
        $user = User::factory()->create();
        $component = Livewire::actingAs($this->admin)->test(Users::class);

        $component->call('toggleActive', $user->id)->assertSee("Deactivated {$user->name}.");
        expect($user->fresh()->is_active)->toBeFalse();

        $component->call('toggleActive', $user->id)->assertSee("Reactivated {$user->name}.");
        expect($user->fresh()->is_active)->toBeTrue();
    });

    it('does not let the admin deactivate themselves', function () {
        Livewire::actingAs($this->admin)
            ->test(Users::class)
            ->call('toggleActive', $this->admin->id)
            ->assertSee('You cannot deactivate your own account.');

        expect($this->admin->fresh()->is_active)->toBeTrue();
    });

    it('blocks deactivating an approver that active users still report to', function () {
        $approver = User::factory()->role(Role::RequestorApprover)->create();
        User::factory()->count(2)->role(Role::Requestor)->create(['approver_id' => $approver->id]);

        Livewire::actingAs($this->admin)
            ->test(Users::class)
            ->call('toggleActive', $approver->id)
            ->assertSee("{$approver->name} is the approver for 2 active users. Assign them a new approver first.");

        expect($approver->fresh()->is_active)->toBeTrue();
    });
});

it('forbids user management actions for anyone but the IT Admin', function () {
    $component = Livewire::actingAs($this->admin)->test(Users::class);
    $target = User::factory()->create();
    $this->admin->update(['role' => Role::Monitor]);

    $component->call('toggleActive', $target->id)->assertForbidden();

    expect($target->fresh()->is_active)->toBeTrue();
});
