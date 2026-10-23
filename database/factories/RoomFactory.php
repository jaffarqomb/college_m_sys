<?php

namespace Database\Factories;

use App\Models\Room;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Room>
 */
class RoomFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $buildings = ['Building A', 'Building B', 'Science Hall', 'Engineering Wing'];

        return [
            'building' => fake()->randomElement($buildings),
            'code' => 'R-'.fake()->unique()->numberBetween(100, 499),
            'capacity' => fake()->randomElement([25, 30, 40, 50, 60, 100]),
            'type' => 'lecture',
        ];
    }

    /**
     * Indicate that the room is a laboratory.
     */
    public function lab(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'lab',
        ]);
    }
}
