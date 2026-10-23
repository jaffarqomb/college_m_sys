<?php

namespace Database\Factories;

use App\Models\Department;
use App\Models\Program;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Program>
 */
class ProgramFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $field = fake()->randomElement([
            'Computer Science', 'Data Science', 'Software Engineering',
            'Business Analytics', 'Electrical Engineering', 'Applied Mathematics',
        ]);

        return [
            'department_id' => Department::factory(),
            'name' => 'Bachelor of Science in '.$field,
            'code' => 'BS-'.Str::upper(Str::substr(Str::squish($field), 0, 3)).fake()->unique()->numberBetween(1, 99),
            'level' => 'bachelor',
            'duration_years' => 4,
            'description' => fake()->paragraph(),
        ];
    }
}
