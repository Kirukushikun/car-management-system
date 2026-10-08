<?php

namespace App\Livewire\Forms;

use App\Enums\Role;
use App\Models\User;
use App\Services\UserDirectory;
use Closure;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Form;

/**
 * Grant / edit form for Users & Roles. People sign in with their central company account, so a
 * user here is created with their central user id (organization standard: the local id must match
 * the id the Auth API returns) and has no usable local password.
 *
 * Enforces the approver chain rules:
 * the approver must be active, hold this role's approver role, share the farm for Responder
 * roles, and must not lead back to this user.
 */
class UserForm extends Form
{
    public ?User $user = null;

    public string $name = '';

    public string $email = '';

    public string $role = '';

    public ?int $farmId = null;

    public ?int $approverId = null;

    public ?int $centralId = null;

    public function setUser(User $user): void
    {
        $this->user = $user;
        $this->name = $user->name;
        $this->email = $user->email;
        $this->role = $user->role->value;
        $this->farmId = $user->farm_id;
        $this->approverId = $user->approver_id;
        $this->centralId = $user->id;
    }

    public function selectedRole(): ?Role
    {
        return Role::tryFrom($this->role);
    }

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        $role = $this->selectedRole();

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($this->user)],
            'role' => ['required', Rule::enum(Role::class)],
            'farmId' => [Rule::requiredIf(fn (): bool => (bool) $role?->isFarmScoped()), 'nullable', Rule::exists('farms', 'id')],
            'approverId' => ['nullable', Rule::exists('users', 'id')->where('is_active', true), $this->approverRule()],
            'centralId' => $this->user ? ['nullable'] : ['required', 'integer', 'min:1', Rule::unique('users', 'id'), $this->inDirectoryRule()],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function messages(): array
    {
        return [
            'farmId.required' => 'Responders and Responder Approvers must belong to a farm.',
            'approverId.exists' => 'The approver must be an active user.',
            'centralId.required' => 'Pick the person from the central directory.',
            'centralId.unique' => 'That central user already has access to this system.',
        ];
    }

    /**
     * Grant access to someone from the directory. Only the id is taken from the browser; name and
     * email are re-read from the directory on the server.
     */
    public function store(): User
    {
        $person = $this->centralId ? app(UserDirectory::class)->find((int) $this->centralId) : null;

        if ($person !== null) {
            $this->name = $person['name'];
            $this->email = $person['email'];
        }

        $this->validate();

        $user = new User([...$this->attributes(), 'password' => Str::random(40)]);
        $user->id = $this->centralId;
        $user->save();

        return $user;
    }

    public function update(): User
    {
        $this->validate();

        $this->user->update($this->attributes());

        return $this->user;
    }

    /**
     * @return array{name: string, email: string, role: Role, farm_id: ?int, approver_id: ?int}
     */
    private function attributes(): array
    {
        $role = $this->selectedRole();

        return [
            'name' => $this->name,
            'email' => $this->email,
            'role' => $role,
            'farm_id' => $role->isFarmScoped() ? $this->farmId : null,
            'approver_id' => $role->approverRole() ? $this->approverId : null,
        ];
    }

    private function inDirectoryRule(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            if (app(UserDirectory::class)->find((int) $value) === null) {
                $fail('That person is not in the central directory.');
            }
        };
    }

    private function approverRule(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            $role = $this->selectedRole();
            $approver = User::find($value);

            if (! $role || ! $approver) {
                return;
            }

            if ($role->approverRole() === null) {
                $fail("A {$role->label()} does not have an approver.");

                return;
            }

            if ($this->user && $approver->is($this->user)) {
                $fail('A user cannot approve their own work.');

                return;
            }

            if ($approver->role !== $role->approverRole()) {
                $fail("The approver of a {$role->label()} must be a {$role->approverRole()->label()}.");

                return;
            }

            if ($role->isFarmScoped() && $approver->farm_id !== $this->farmId) {
                $fail('The approver must belong to the same farm.');

                return;
            }

            if ($this->user && $this->chainLeadsBackToUser($approver)) {
                $fail("{$approver->name}'s approver chain already leads back to {$this->user->name}.");
            }
        };
    }

    /**
     * Walks up from the proposed approver; true if the chain reaches the user being edited.
     */
    private function chainLeadsBackToUser(User $approver): bool
    {
        $visited = [];
        $current = $approver;

        while ($current && ! in_array($current->id, $visited, true)) {
            if ($current->approver_id === $this->user->id) {
                return true;
            }

            $visited[] = $current->id;
            $current = $current->approver;
        }

        return false;
    }
}
