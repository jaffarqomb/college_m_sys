<?php

namespace Database\Factories;

use App\Models\Department;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Department>
 */
class DepartmentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->randomElement([
            'Computer Science', 'Mathematics', 'Physics', 'Chemistry',
            'Business Administration', 'Economics', 'English Literature',
            'Mechanical Engineering', 'Biological Sciences', 'Political Science',
        ]);

        return [
            'name' => 'Department of '.$name,
            'code' => strtoupper(Str::substr($name, 0, 3)).fake()->unique()->numberBetween(1, 99),
            'description' => fake()->sentence(),
        ];
    }
}
