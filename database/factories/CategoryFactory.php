<?php

namespace Database\Factories;

use App\Models\BusinessLine;
use App\Models\Category;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Category>
 */
class CategoryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'business_line_id' => BusinessLine::factory(),
            'name' => fake()->unique()->words(3, true),
            'response_days' => 1,
            'implementation_days' => 5,
        ];
    }
}
