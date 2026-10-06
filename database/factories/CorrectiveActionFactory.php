<?php

namespace Database\Factories;

use App\Models\CarResponse;
use App\Models\CorrectiveAction;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CorrectiveAction>
 */
class CorrectiveActionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'car_response_id' => CarResponse::factory(),
            'position' => 1,
            'description' => 'Move dirty eggs to cold storage on the day of collection and dispatch first-in, first-out.',
            'responsible' => fake()->name(),
            'starts_on' => today(),
            'ends_on' => today()->addDays(5),
        ];
    }
}
