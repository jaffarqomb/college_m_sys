<?php

namespace Database\Factories;

use App\Models\ContactMessage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ContactMessage>
 */
class ContactMessageFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->safeEmail(),
            'subject' => fake()->randomElement(['Admissions Question', 'Program Inquiry', 'Fee Structure', 'Campus Tour Request']),
            'message' => fake()->paragraph(),
            'is_read' => false,
            'responded_at' => null,
        ];
    }

    /**
     * Indicate that the message has been read by staff.
     */
    public function read(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_read' => true,
            'responded_at' => now()->subHours(fake()->numberBetween(1, 48)),
        ]);
    }
}
