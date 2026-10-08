<?php

use App\Enums\Role;
use App\Livewire\Admin\Users;
use App\Models\Farm;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

/**
 * The central directory as the guide's pasted real response shows it: a bare array, split names,
 * ids encrypted with the shared APP_KEY.
 *
 * @param  list<array{0: int, 1: string, 2: string, 3: string}>  $people  [id, first name, last name, email]
 */
function fakeDirectory(array $people): void
{
    config(['services.user_api.endpoint' => 'https://auth.test/api/v1/users']);

    Http::fake(['auth.test/api/v1/users' => Http::response(array_map(fn (array $person): array => [
        'id' => Crypt::encryptString((string) $person[0]),
        'first_name' => $person[1],
        'last_name' => $person[2],
        'middle_name' => null,
        'email' => $person[3],
        'created_at' => null,
        'updated_at' => '2026-07-14T05:45:08.000000Z',
    ], $people))]);
}

beforeEach(function () {
    $this->admin = User::factory()->role(Role::Admin)->create();

    fakeDirectory([
        [5120, 'Juan', 'Dela Cruz', 'juan@bfcgroup.test'],
        [5121, 'Nena', 'Responder', 'nena@bfcgroup.test'],
        [5122, 'Quinn', 'Assurance', 'qa@bfcgroup.test'],
        [5123, 'Maria Christina', 'Santos', 'm.santos@bfcgroup.test'],
    ]);
});

describe('the central directory', function () {
    it('lists everyone in the directory with their access in this system', function () {
        User::factory()->role(Role::Monitor)->create(['id' => 5122, 'email' => 'qa@bfcgroup.test']);

        Livewire::actingAs($this->admin)
            ->test(Users::class)
            ->assertSee(['Central directory', '4 people', 'Juan Dela Cruz', 'Maria Christina Santos', 'No access', 'Monitor']);
    });

    it('filters the directory by name or email', function () {
        Livewire::actingAs($this->admin)
            ->test(Users::class)
            ->set('search', 'santos')
            ->assertSee('Maria Christina Santos')
            ->assertDontSee('Juan Dela Cruz')
            ->set('search', 'juan@')
            ->assertSee('Juan Dela Cruz');
    });

    it('caches the directory so searching does not call the API each time, and refreshes on demand', function () {
        $component = Livewire::actingAs($this->admin)->test(Users::class)
            ->set('search', 'a')->set('search', 'ab')->set('search', '');

        Http::assertSentCount(1);

        $component->call('refreshDirectory')->assertSee('Directory refreshed.');

        Http::assertSentCount(2);
    });

    it('explains when the directory cannot be loaded', function () {
        Cache::flush();
        config(['services.user_api.endpoint' => 'https://down.test/api/v1/users']);
        Http::fake(['down.test/*' => Http::response(['message' => 'error'], 500)]);

        Livewire::actingAs($this->admin)
            ->test(Users::class)
            ->assertSee('The user directory answered with an error (HTTP 500).');
    });
});

describe('granting access', function () {
    it('grants a person from the directory under their central id, with an approver and no usable local password', function () {
        $approver = User::factory()->role(Role::RequestorApprover)->create();

        Livewire::actingAs($this->admin)
            ->test(Users::class)
            ->call('grant', 5120)
            ->assertSet('form.centralId', 5120)
            ->assertSet('form.name', 'Juan Dela Cruz')
            ->assertSet('form.email', 'juan@bfcgroup.test')
            ->set('form.role', Role::Requestor->value)
            ->set('form.approverId', $approver->id)
            ->call('save')
            ->assertHasNoErrors()
            ->assertSee('Granted Juan Dela Cruz access as Requestor.')
            ->assertSet('showForm', false);

        expect(User::findOrFail(5120))
            ->email->toBe('juan@bfcgroup.test')
            ->role->toBe(Role::Requestor)
            ->approver_id->toBe($approver->id)
            ->is_active->toBeTrue()
            ->is_sample->toBeFalse()
            ->and(Hash::check('password', User::findOrFail(5120)->password))->toBeFalse();
    });

    it('takes the name and email from the directory, not from the browser', function () {
        Livewire::actingAs($this->admin)
            ->test(Users::class)
            ->call('grant', 5120)
            ->set('form.name', 'Tampered Name')
            ->set('form.email', 'attacker@evil.test')
            ->set('form.role', Role::Monitor->value)
            ->call('save')
            ->assertHasNoErrors();

        expect(User::findOrFail(5120))
            ->name->toBe('Juan Dela Cruz')
            ->email->toBe('juan@bfcgroup.test');
    });

    it('refuses a central id that is not in the directory', function () {
        Livewire::actingAs($this->admin)
            ->test(Users::class)
            ->call('grant', 5120)
            ->set('form.centralId', 99999)
            ->set('form.name', 'Ghost')
            ->set('form.email', 'ghost@bfcgroup.test')
            ->set('form.role', Role::Monitor->value)
            ->call('save')
            ->assertHasErrors('form.centralId')
            ->assertSee('That person is not in the central directory.');

        expect(User::find(99999))->toBeNull();
    });

    it('opens the edit form for someone who already has an account', function () {
        $existing = User::factory()->role(Role::Monitor)->create(['id' => 5122, 'email' => 'qa@bfcgroup.test']);

        Livewire::actingAs($this->admin)
            ->test(Users::class)
            ->call('grant', 5122)
            ->assertSet('form.user.id', $existing->id)
            ->assertSee('Edit '.$existing->name);
    });

    it('rejects an email that another account already uses', function () {
        User::factory()->create(['email' => 'juan@bfcgroup.test']);

        Livewire::actingAs($this->admin)
            ->test(Users::class)
            ->call('grant', 5120)
            ->set('form.role', Role::Monitor->value)
            ->call('save')
            ->assertHasErrors(['form.email' => 'unique']);
    });

    it('requires a farm for responder roles', function () {
        Livewire::actingAs($this->admin)
            ->test(Users::class)
            ->call('grant', 5121)
            ->set('form.role', Role::Responder->value)
            ->call('save')
            ->assertHasErrors(['form.farmId' => 'required'])
            ->assertSee('Responders and Responder Approvers must belong to a farm.');
    });

    it('treats the empty farm option as no farm', function () {
        Livewire::actingAs($this->admin)
            ->test(Users::class)
            ->call('grant', 5121)
            ->set('form.role', Role::Responder->value)
            ->set('form.farmId', '')
            ->assertSet('form.farmId', null)
            ->call('save')
            ->assertHasErrors(['form.farmId' => 'required']);
    });

    it('drops the farm for roles that are not tied to a farm', function () {
        $farm = Farm::factory()->create();

        Livewire::actingAs($this->admin)
            ->test(Users::class)
            ->call('grant', 5122)
            ->set('form.role', Role::Monitor->value)
            ->set('form.farmId', $farm->id)
            ->call('save')
            ->assertHasNoErrors();

        expect(User::findOrFail(5122)->farm_id)->toBeNull();
    });
});

describe('approver chain', function () {
    function saveResponderWithApprover(User $admin, Farm $farm, User $approver): Testable
    {
        return Livewire::actingAs($admin)
            ->test(Users::class)
            ->call('grant', 5121)
            ->set('form.role', Role::Responder->value)
            ->set('form.farmId', $farm->id)
            ->set('form.approverId', $approver->id)
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
            ->call('grant', 5121)
            ->set('form.role', Role::Responder->value)
            ->set('form.farmId', $pfcApprover->farm_id)
            ->assertViewHas('approverOptions', fn ($options) => $options->pluck('name')->all() === ['Pfc Approver']);
    });
});

describe('editing users', function () {
    it('keeps the stored password hash untouched when editing', function () {
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

    it('keeps the central user id when editing', function () {
        $user = User::factory()->create();

        Livewire::actingAs($this->admin)
            ->test(Users::class)
            ->call('edit', $user->id)
            ->assertSet('form.centralId', $user->id)
            ->set('form.centralId', 777777)
            ->set('form.name', 'Renamed')
            ->call('save')
            ->assertHasNoErrors();

        expect(User::find($user->id)->name)->toBe('Renamed')
            ->and(User::find(777777))->toBeNull();
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
