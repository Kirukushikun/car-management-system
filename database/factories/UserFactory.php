<?php

namespace Database\Factories;

use App\Enums\Role;
use App\Models\Farm;
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
            'farm_id' => null,
            'is_active' => true,
            'is_sample' => false,
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * Give the user a role. Farm-scoped roles (Responder, Responder Approver) are attached to the
     * named farm — PFC unless another is passed — which is created if it does not exist yet.
     */
    public function role(Role $role, ?string $farmName = null): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => $role,
            'farm_id' => $role->isFarmScoped()
                ? Farm::firstOrCreate(['name' => $farmName ?? 'PFC'])->id
                : null,
        ]);
    }

    /**
     * A TestSeeder-style sample account: flagged, with the shared sample password.
     */
    public function sample(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_sample' => true,
            'password' => Hash::make(config('login.sample_password')),
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
