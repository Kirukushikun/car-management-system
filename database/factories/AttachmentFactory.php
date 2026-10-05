<?php

namespace Database\Factories;

use App\Models\Attachment;
use App\Models\Car;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Attachment row only — tests that need the file itself put it on a faked disk.
 *
 * @extends Factory<Attachment>
 */
class AttachmentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'attachable_type' => (new Car)->getMorphClass(),
            'attachable_id' => Car::factory(),
            'collection' => Attachment::PROBLEM_EVIDENCE,
            'disk' => 'local',
            'path' => 'cars/1/'.fake()->uuid().'.jpg',
            'original_name' => 'photo.jpg',
            'mime_type' => 'image/jpeg',
            'size' => 204800,
            'uploaded_by' => User::factory(),
        ];
    }
}
