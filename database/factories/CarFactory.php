<?php

namespace Database\Factories;

use App\Enums\CarStatus;
use App\Enums\ComplaintType;
use App\Enums\Role;
use App\Models\Car;
use App\Models\CarResponse;
use App\Models\Category;
use App\Models\Farm;
use App\Models\IssuedToUnit;
use App\Models\Subcategory;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Builds a consistent CAR directly in a given status — for tests that need a CAR mid-workflow.
 * Real CARs are created and moved only through CarWorkflow.
 *
 * @extends Factory<Car>
 */
class CarFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $issuedOn = CarbonImmutable::today();

        return [
            'reference' => fn (): string => sprintf('CAR-%d-%04d', $issuedOn->year, fake()->unique()->numberBetween(1, 9999)),
            'status' => CarStatus::AwaitingRelease,
            'current_round' => 1,
            'requestor_id' => fn (): int => User::factory()->role(Role::Requestor)->create()->id,
            'issued_by' => fake()->name(),
            'complainant' => fake()->company(),
            'complaint_type' => ComplaintType::Product,
            'problem_details' => fake()->paragraph(),
            'farm_id' => fn (): int => Farm::firstOrCreate(['name' => 'PFC'])->id,
            'issued_to_unit_id' => fn (): int => IssuedToUnit::factory()->create()->id,
            'category_id' => fn (array $attributes): int => Category::factory()->create([
                'business_line_id' => IssuedToUnit::find($attributes['issued_to_unit_id'])->business_line_id,
                'response_days' => 3,
                'implementation_days' => 10,
            ])->id,
            'subcategory_id' => fn (array $attributes): int => Subcategory::factory()->create(['category_id' => $attributes['category_id']])->id,
            'issued_on' => $issuedOn,
            'response_days' => 3,
            'implementation_days' => 10,
            'response_due_on' => $issuedOn->addDays(3),
            'implementation_due_on' => $issuedOn->addDays(10),
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (Car $car): void {
            foreach (range(1, $car->current_round) as $number) {
                $car->rounds()->firstOrCreate(['number' => $number], ['opened_by_action' => $number === 1 ? 'submit' : 'mark_not_effective']);
            }
        });
    }

    /**
     * Put the CAR in a workflow status, with the timestamps that status implies.
     */
    public function status(CarStatus $status): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => $status,
            'released_at' => $status === CarStatus::AwaitingRelease || $status === CarStatus::ReturnedToRequestor ? null : now(),
            'closed_at' => $status === CarStatus::ClosedAccepted ? now() : null,
            'voided_at' => $status === CarStatus::Voided ? now() : null,
        ]);
    }

    /**
     * Give the CAR's current round a complete Phase II response (draft until submitted).
     */
    public function withResponse(?User $preparedBy = null, bool $submitted = false): static
    {
        return $this->afterCreating(function (Car $car) use ($preparedBy, $submitted): void {
            CarResponse::factory()->create([
                'car_id' => $car->id,
                'car_round_id' => $car->currentRound()->first()->id,
                'prepared_by' => $preparedBy?->id,
                'submitted_at' => $submitted ? now() : null,
            ]);
        });
    }

    /**
     * Answerable by the named farm's Responders.
     */
    public function forFarm(string $name): static
    {
        return $this->state(fn (array $attributes): array => [
            'farm_id' => Farm::firstOrCreate(['name' => $name])->id,
        ]);
    }
}
