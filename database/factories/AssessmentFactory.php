<?php

namespace Database\Factories;

use App\Models\Assessment;
use App\Models\CourseSection;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Assessment>
 */
class AssessmentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $titles = ['Quiz 1', 'Quiz 2', 'Assignment', 'Midterm Exam', 'Final Exam', 'Lab Work'];

        return [
            'section_id' => CourseSection::factory(),
            'title' => fake()->unique()->randomElement($titles),
            'type' => fake()->randomElement(['quiz', 'assignment', 'midterm', 'final', 'lab']),
            'weight' => fake()->randomElement([10, 15, 20, 25, 40]),
            'max_score' => 100,
            'due_date' => now()->addWeeks(2),
            'conducted_at' => null,
        ];
    }

    /**
     * Indicate that the assessment is a final exam.
     */
    public function final(): static
    {
        return $this->state(fn (array $attributes) => [
            'title' => 'Final Exam',
            'type' => 'final',
            'weight' => 40,
        ]);
    }
}
