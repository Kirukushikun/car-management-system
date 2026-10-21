<?php

namespace Database\Factories;

use App\Models\ComplaintType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ComplaintType>
 */
class ComplaintTypeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => ucfirst(fake()->unique()->words(2, true)).' Nonconformity',
        ];
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }
}
