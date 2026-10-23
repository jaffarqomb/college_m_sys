<?php

namespace Database\Factories;

use App\Models\ClassSession;
use App\Models\CourseSection;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ClassSession>
 */
class ClassSessionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'section_id' => CourseSection::factory(),
            'session_date' => fake()->dateTimeBetween('-2 months', 'now')->format('Y-m-d'),
            'topic' => fake()->sentence(),
            'status' => 'held',
        ];
    }

    /**
     * Indicate that the session has not taken place yet.
     */
    public function planned(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'planned',
        ]);
    }
}
