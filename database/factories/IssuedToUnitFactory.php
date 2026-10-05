<?php

namespace Database\Factories;

use App\Models\BusinessLine;
use App\Models\IssuedToUnit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<IssuedToUnit>
 */
class IssuedToUnitFactory extends Factory
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
            'name' => fake()->unique()->words(2, true),
        ];
    }
}
