<?php

namespace Database\Factories;

use App\Models\BusinessLine;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BusinessLine>
 */
class BusinessLineFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => strtoupper(fake()->unique()->lexify('LINE ????')),
        ];
    }
}
