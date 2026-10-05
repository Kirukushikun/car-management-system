<?php

namespace Database\Factories;

use App\Enums\CarAction;
use App\Enums\CarStatus;
use App\Models\Car;
use App\Models\CarEvent;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CarEvent>
 */
class CarEventFactory extends Factory
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
            'round' => 1,
            'action' => CarAction::Submit,
            'from_status' => null,
            'to_status' => CarStatus::AwaitingRelease,
            'actor_id' => User::factory(),
            'note' => null,
        ];
    }
}
