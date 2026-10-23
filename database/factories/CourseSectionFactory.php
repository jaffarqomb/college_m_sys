<?php

namespace Database\Factories;

use App\Models\AcademicTerm;
use App\Models\Course;
use App\Models\CourseSection;
use App\Models\Instructor;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CourseSection>
 */
class CourseSectionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'course_id' => Course::factory(),
            'term_id' => AcademicTerm::factory(),
            'instructor_id' => Instructor::factory(),
            'section_number' => '01',
            'capacity' => 30,
            'requires_lab' => false,
            'status' => 'open',
        ];
    }

    /**
     * Indicate that the section belongs to the current term.
     */
    public function inCurrentTerm(): static
    {
        return $this->state(fn (array $attributes) => [
            'term_id' => AcademicTerm::factory()->current(),
        ]);
    }

    /**
     * Indicate that the section is closed for enrollment.
     */
    public function closed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'closed',
        ]);
    }

    /**
     * Indicate that the section requires a laboratory room.
     */
    public function requiresLab(): static
    {
        return $this->state(fn (array $attributes) => [
            'requires_lab' => true,
        ]);
    }
}
