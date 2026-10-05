<?php

namespace Database\Factories;

use App\Enums\CarAction;
use App\Models\Car;
use App\Models\CarRound;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CarRound>
 */
class CarRoundFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'car_id' => Car::factory(),
            'number' => 1,
            'opened_by_action' => CarAction::Submit,
            'due_on' => null,
        ];
    }
}
