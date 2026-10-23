<?php

namespace Database\Factories;

use App\Models\ChatMessage;
use App\Models\ChatSession;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ChatMessage>
 */
class ChatMessageFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'chat_session_id' => ChatSession::factory(),
            'role' => 'user',
            'content' => fake()->sentence(),
            'intent' => null,
        ];
    }

    /**
     * Indicate the message was sent by the bot.
     */
    public function fromBot(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => 'bot',
        ]);
    }
}
