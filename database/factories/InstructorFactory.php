<?php

namespace Database\Factories;

use App\Enums\UserRole;
use App\Models\Department;
use App\Models\Instructor;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Instructor>
 */
class InstructorFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory()->asRole(UserRole::Instructor),
            'department_id' => Department::factory(),
            'employee_number' => 'EMP-'.fake()->unique()->numberBetween(1000, 9999),
            'title' => fake()->randomElement(['Prof.', 'Dr.', 'Mr.', 'Ms.']),
            'office' => fake()->randomElement(['A-101', 'B-204', 'C-310', 'D-118']),
            'office_hours' => 'Sun-Thu 10:00 - 12:00',
            'is_head' => false,
        ];
    }

    /**
     * Indicate that the instructor is the department head.
     */
    public function departmentHead(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_head' => true,
        ]);
    }

    /**
     * Indicate that the instructor belongs to an existing user account.
     */
    public function forExistingUser(User $user): static
    {
        return $this->state(fn (array $attributes) => [
            'user_id' => $user->id,
        ]);
    }

    /**
     * Expose the underlying user factory for further customization.
     */
    public static function user(): UserFactory
    {
        return User::factory()->asRole(UserRole::Instructor);
    }
}
