<?php

namespace Database\Factories;

use App\Models\Assessment;
use App\Models\Enrollment;
use App\Models\Grade;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Grade>
 */
class GradeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'assessment_id' => Assessment::factory(),
            'enrollment_id' => Enrollment::factory(),
            'score' => fake()->randomFloat(2, 40, 100),
            'feedback' => fake()->optional()->sentence(),
        ];
    }

    /**
     * Indicate that the grade has not been posted yet.
     */
    public function ungraded(): static
    {
        return $this->state(fn (array $attributes) => [
            'score' => null,
            'feedback' => null,
        ]);
    }
}
