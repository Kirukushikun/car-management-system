<?php

namespace Database\Factories;

use App\Models\Car;
use App\Models\CarResponse;
use App\Models\CorrectiveAction;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CarResponse>
 */
class CarResponseFactory extends Factory
{
    /**
     * A complete response with one corrective action, attached to the CAR's current round.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'car_id' => Car::factory(),
            'car_round_id' => fn (array $attributes): int => Car::find($attributes['car_id'])->currentRound->id,
            'containment_actions' => 'Segregated the affected batch and held it from dispatch.',
            'containment_starts_on' => today(),
            'containment_ends_on' => today()->addDay(),
            'containment_responsible' => fake()->name(),
            'root_cause' => 'Eggs were dispatched three days after collection without cold storage.',
            'root_cause_responsible' => fake()->name(),
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (CarResponse $response): void {
            if ($response->correctiveActions()->doesntExist()) {
                CorrectiveAction::factory()->create([
                    'car_response_id' => $response->id,
                    'starts_on' => $response->containment_ends_on ?? today(),
                    'ends_on' => ($response->containment_ends_on ?? today())->addDays(5),
                ]);
            }
        });
    }

    /**
     * A blank draft: nothing filled in yet.
     */
    public function draft(): static
    {
        return $this->state(fn (array $attributes): array => [
            'containment_actions' => null,
            'containment_starts_on' => null,
            'containment_ends_on' => null,
            'containment_responsible' => null,
            'root_cause' => null,
            'root_cause_responsible' => null,
        ]);
    }
}
