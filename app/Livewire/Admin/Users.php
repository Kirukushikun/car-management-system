<?php

namespace App\Livewire\Admin;

use App\Enums\Role;
use App\Livewire\Forms\UserForm;
use App\Models\Farm;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Users & Roles: create accounts, assign role / farm / approver, deactivate and reactivate.
 * Accounts are never deleted — CAR history keeps pointing at them.
 */
#[Title('Users & Roles')]
class Users extends Component
{
    public UserForm $form;

    public bool $showForm = false;

    public ?string $notice = null;

    public function mount(): void
    {
        $this->authorize('administer');
    }

    public function create(): void
    {
        $this->authorize('administer');

        $this->form->reset();
        $this->form->resetErrorBag();
        $this->form->role = Role::Requestor->value;
        $this->showForm = true;
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
            $this->notice = "Created {$user->name} as {$user->role->label()}. Share the initial password with them directly.";
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

    public function render(): View
    {
        return view('livewire.admin.users', [
            'users' => User::with(['farm', 'approver'])->orderBy('role')->orderBy('name')->get(),
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
