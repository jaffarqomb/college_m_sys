<?php

namespace Database\Factories;

use App\Enums\UserRole;
use App\Models\Program;
use App\Models\Student;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Student>
 */
class StudentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory()->asRole(UserRole::Student),
            'program_id' => Program::factory(),
            'student_number' => 'S-'.fake()->unique()->numberBetween(10000, 99999),
            'admission_date' => fake()->dateTimeBetween('-3 years', 'now')->format('Y-m-d'),
            'status' => 'active',
        ];
    }

    /**
     * Indicate that the student is inactive/graduated.
     */
    public function graduated(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'graduated',
        ]);
    }

    /**
     * Indicate that the student belongs to an existing user account.
     */
    public function forExistingUser(User $user): static
    {
        return $this->state(fn (array $attributes) => [
            'user_id' => $user->id,
        ]);
    }
}
