<?php

namespace Database\Factories;

use App\Models\CourseSection;
use App\Models\Room;
use App\Models\SectionSchedule;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SectionSchedule>
 */
class SectionScheduleFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'section_id' => CourseSection::factory(),
            'room_id' => Room::factory(),
            'day_of_week' => fake()->numberBetween(1, 5),
            'start_time' => '09:00:00',
            'end_time' => '10:30:00',
        ];
    }

    /**
     * Indicate the schedule slot occurs at a specific day/time/room.
     */
    public function at(int $day, string $start, string $end, ?Room $room = null): static
    {
        return $this->state(fn (array $attributes) => [
            'day_of_week' => $day,
            'start_time' => $start,
            'end_time' => $end,
            'room_id' => $room?->id,
        ]);
    }
}
