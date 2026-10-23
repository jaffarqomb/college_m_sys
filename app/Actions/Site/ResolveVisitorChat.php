<?php

namespace App\Actions\Site;

use Illuminate\Support\Arr;
use Illuminate\Support\Str;

/**
 * Resolves a visitor question into a bot reply using the configured
 * FAQ intents. Pure rule-based - no external services required.
 */
class ResolveVisitorChat
{
    /**
     * @return array{content: string, intent: string|null}
     */
    public function handle(string $question): array
    {
        $question = Str::lower(trim($question));

        foreach (config('chatbot.intents') as $intent => $intentConfig) {
            foreach (Arr::wrap($intentConfig['patterns']) as $pattern) {
                if (Str::contains($question, $pattern)) {
                    return [
                        'content' => $intentConfig['answer'],
                        'intent' => $intent,
                    ];
                }
            }
        }

        return [
            'content' => config('chatbot.messages.fallback'),
            'intent' => null,
        ];
    }
}
