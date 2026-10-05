<?php

namespace App\Livewire\Forms;

use App\Enums\Role;
use App\Models\User;
use Closure;
use Illuminate\Validation\Rule;
use Livewire\Form;

/**
 * Create / edit form for Users & Roles. Enforces the approver chain rules:
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

    public string $password = '';

    public function setUser(User $user): void
    {
        $this->user = $user;
        $this->name = $user->name;
        $this->email = $user->email;
        $this->role = $user->role->value;
        $this->farmId = $user->farm_id;
        $this->approverId = $user->approver_id;
        $this->password = '';
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
            'password' => [$this->user ? 'nullable' : 'required', 'string', 'min:8'],
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
        ];
    }

    public function store(): User
    {
        $this->validate();

        return User::create([...$this->attributes(), 'password' => $this->password]);
    }

    public function update(): User
    {
        $this->validate();

        $attributes = $this->attributes();

        if ($this->password !== '') {
            $attributes['password'] = $this->password;
        }

        $this->user->update($attributes);

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
