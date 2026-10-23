<?php

namespace Database\Factories;

use App\Models\CourseSection;
use App\Models\Enrollment;
use App\Models\Student;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Enrollment>
 */
class EnrollmentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'student_id' => Student::factory(),
            'section_id' => CourseSection::factory(),
            'status' => 'active',
            'enrolled_at' => now(),
            'dropped_at' => null,
        ];
    }

    /**
     * Indicate that the enrollment was dropped.
     */
    public function dropped(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'dropped',
            'dropped_at' => now(),
        ]);
    }

    /**
     * Indicate that the enrollment is completed.
     */
    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'completed',
        ]);
    }
}
