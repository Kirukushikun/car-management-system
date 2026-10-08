<?php

namespace App\Livewire\Admin;

use App\Enums\Role;
use App\Livewire\Forms\UserForm;
use App\Models\Farm;
use App\Models\User;
use App\Services\UserDirectory;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Users & Roles: grant access to people from the central directory (by central user id), assign
 * role / farm / approver, deactivate and reactivate.
 * Accounts are never deleted — CAR history keeps pointing at them.
 */
#[Title('Users & Roles')]
class Users extends Component
{
    public UserForm $form;

    public bool $showForm = false;

    public ?string $notice = null;

    public string $search = '';

    public function mount(): void
    {
        $this->authorize('administer');
    }

    /**
     * Start granting access to someone from the central directory. People who already have an
     * account open in the edit form instead.
     */
    public function grant(int $centralId, UserDirectory $directory): void
    {
        $this->authorize('administer');

        $person = $directory->find($centralId);

        if ($person === null) {
            $this->notice = 'That person is no longer in the central directory. Try Refresh.';

            return;
        }

        if ($existing = User::find($centralId)) {
            $this->edit($existing);

            return;
        }

        $this->form->reset();
        $this->form->resetErrorBag();
        $this->form->centralId = $person['id'];
        $this->form->name = $person['name'];
        $this->form->email = $person['email'];
        $this->form->role = Role::Requestor->value;
        $this->showForm = true;
    }

    public function refreshDirectory(UserDirectory $directory): void
    {
        $this->authorize('administer');

        $directory->refresh();
        $this->notice = 'Directory refreshed.';
    }

    public function edit(User $user): void
    {
        $this->authorize('administer');

        $this->form->resetErrorBag();
        $this->form->setUser($user);
        $this->showForm = true;
    }

    public function updatedFormRole(): void
    {
        $this->form->approverId = null;
    }

    public function updatedFormFarmId(): void
    {
        $this->form->approverId = null;
    }

    public function save(): void
    {
        $this->authorize('administer');

        if ($this->form->user) {
            if (! $this->guardRoleChange($this->form->user)) {
                return;
            }

            $user = $this->form->update();
            $this->notice = "Saved changes to {$user->name}.";
        } else {
            $user = $this->form->store();
            $this->notice = "Granted {$user->name} access as {$user->role->label()}. They sign in with their company account.";
        }

        $this->cancel();
    }

    public function cancel(): void
    {
        $this->form->reset();
        $this->form->resetErrorBag();
        $this->showForm = false;
    }

    public function toggleActive(User $user): void
    {
        $this->authorize('administer');

        if ($user->is(auth()->user())) {
            $this->notice = 'You cannot deactivate your own account.';

            return;
        }

        if ($user->is_active && ($count = $this->approveeCount($user)) > 0) {
            $this->notice = "{$user->name} is the approver for {$count} active ".str('user')->plural($count).'. Assign them a new approver first.';

            return;
        }

        $user->update(['is_active' => ! $user->is_active]);
        $this->notice = $user->is_active ? "Reactivated {$user->name}." : "Deactivated {$user->name}. They are signed out on their next request.";
    }

    public function render(UserDirectory $directory): View
    {
        $users = User::with(['farm', 'approver'])->orderBy('role')->orderBy('name')->get();
        $listing = $directory->all();
        $term = mb_strtolower(trim($this->search));
        $matches = collect($listing['users'])
            ->filter(fn (array $person): bool => $term === ''
                || str_contains(mb_strtolower($person['name']), $term)
                || str_contains(mb_strtolower($person['email']), $term));

        return view('livewire.admin.users', [
            'users' => $users,
            'directory' => $matches->take(50)->values(),
            'directoryTotal' => count($listing['users']),
            'directoryMatches' => $matches->count(),
            'directoryError' => $listing['error'],
            'undecryptable' => $listing['undecryptable'],
            'localById' => $users->keyBy('id'),
            'roles' => Role::cases(),
            'farms' => Farm::orderBy('name')->get(),
            'approverOptions' => $this->approverOptions(),
            'selectedRole' => $this->form->selectedRole(),
        ]);
    }

    /**
     * An admin may not demote themselves, and a user others report to may not change role
     * until those people have a new approver.
     */
    private function guardRoleChange(User $user): bool
    {
        if ($user->role->value === $this->form->role) {
            return true;
        }

        if ($user->is(auth()->user())) {
            $this->form->addError('role', 'You cannot change your own role.');

            return false;
        }

        if (($count = $this->approveeCount($user)) > 0) {
            $this->form->addError('role', "{$user->name} is the approver for {$count} active ".str('user')->plural($count).'. Assign them a new approver before changing this role.');

            return false;
        }

        return true;
    }

    private function approveeCount(User $user): int
    {
        return User::active()->where('approver_id', $user->id)->count();
    }

    /**
     * Active users who may approve for the role (and farm) currently picked in the form.
     *
     * @return Collection<int, User>
     */
    private function approverOptions(): Collection
    {
        $approverRole = $this->form->selectedRole()?->approverRole();

        if (! $approverRole) {
            return new Collection;
        }

        return User::active()
            ->where('role', $approverRole)
            ->when($this->form->user, fn ($query) => $query->whereKeyNot($this->form->user->id))
            ->when($approverRole->isFarmScoped(), fn ($query) => $query->where('farm_id', $this->form->farmId))
            ->orderBy('name')
            ->get();
    }
}
