<?php

namespace Database\Factories;

use App\Models\Course;
use App\Models\Department;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Course>
 */
class CourseFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $subjects = ['Introduction to Programming', 'Data Structures', 'Database Systems', 'Operating Systems', 'Linear Algebra', 'Calculus I', 'Molecular Biology', 'Microeconomics'];
        $title = fake()->unique()->randomElement($subjects);

        return [
            'department_id' => Department::factory(),
            'code' => Str::upper(Str::substr(Str::squish($title), 0, 2)).fake()->unique()->numberBetween(100, 599),
            'title' => $title,
            'credits' => fake()->randomElement([2, 3, 3, 4]),
            'year_level' => 1,
            'description' => fake()->sentence(),
            'requires_lab' => false,
            'is_active' => true,
        ];
    }

    /**
     * Indicate that the course requires a laboratory room.
     */
    public function withLab(): static
    {
        return $this->state(fn (array $attributes) => [
            'requires_lab' => true,
        ]);
    }
}
