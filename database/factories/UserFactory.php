<?php

namespace Database\Factories;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'role' => Role::Requestor,
            'scope' => 'Sales',
            'is_active' => true,
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * Give the user a role. Farm-scoped roles default to PFC unless a scope is passed.
     */
    public function role(Role $role, ?string $scope = null): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => $role,
            'scope' => $scope ?? match ($role) {
                Role::Requestor, Role::RequestorApprover => 'Sales',
                Role::Responder, Role::ResponderApprover => 'PFC',
                Role::Monitor => 'All farms',
                Role::Admin => 'IT',
            },
        ]);
    }

    /**
     * Indicate that the user has been deactivated by an admin.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }
}
