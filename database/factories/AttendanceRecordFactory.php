<?php

namespace Database\Factories;

use App\Models\AttendanceRecord;
use App\Models\ClassSession;
use App\Models\Enrollment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AttendanceRecord>
 */
class AttendanceRecordFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'class_session_id' => ClassSession::factory(),
            'enrollment_id' => Enrollment::factory(),
            'status' => fake()->randomElement(['present', 'present', 'present', 'absent', 'late']),
        ];
    }

    /**
     * Indicate the student was present.
     */
    public function present(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'present',
        ]);
    }

    /**
     * Indicate the student was absent.
     */
    public function absent(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'absent',
        ]);
    }
}
