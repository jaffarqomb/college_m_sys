<?php

namespace App\Actions\Site;

use App\Models\ChatSession;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;

/**
 * Coordinates the public website chatbot: rate limiting, question
 * resolution, and conversation persistence.
 */
class ResolveBotReply
{
    public function __construct(private readonly ResolveVisitorChat $resolveVisitorChat) {}

    /**
     * Handle a visitor message for the given chat session.
     *
     * @return array{reply: string, intent: string|null}
     *
     * @throws ValidationException when the visitor is rate limited
     */
    public function handle(ChatSession $session, string $question): array
    {
        $session->messages()->create([
            'role' => 'user',
            'content' => $question,
        ]);

        $key = 'chatbot-cooldown:'.$session->visitor_token;

        if (Cache::has($key)) {
            return $this->persistBotReply(
                $session,
                content: config('chatbot.messages.rate_limited'),
            );
        }

        Cache::put($key, true, config('chatbot.cooldown'));

        $resolution = $this->resolveVisitorChat->handle($question);

        $this->persistBotReply(
            $session,
            content: $resolution['content'],
            intent: $resolution['intent'],
        );

        return [
            'reply' => $resolution['content'],
            'intent' => $resolution['intent'],
        ];
    }

    /**
     * @return array{reply: string, intent: string|null}
     */
    private function persistBotReply(ChatSession $session, string $content, ?string $intent = null): array
    {
        $session->messages()->create([
            'role' => 'bot',
            'content' => $content,
            'intent' => $intent,
        ]);

        return [
            'reply' => $content,
            'intent' => $intent,
        ];
    }
}
