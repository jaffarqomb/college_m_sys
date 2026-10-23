<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

final class OllamaService
{
    /**
     * Whether a local Ollama server answers at the configured endpoint.
     */
    public function isAvailable(): bool
    {
        try {
            return (int) Http::baseUrl($this->url())
                ->timeout(2)
                ->get('/api/tags')
                ->status() < 500;
        } catch (ConnectionException) {
            return false;
        }
    }

    /**
     * Ask the locally installed model, returning its reply text.
     */
    public function chat(string $prompt): string
    {
        try {
            $response = Http::baseUrl($this->url())
                ->timeout(120)
                ->post('/api/chat', [
                    'model' => $this->model(),
                    'messages' => [
                        [
                            'role' => 'user',
                            'content' => $prompt,
                        ],
                    ],
                    'stream' => false,
                ]);
        } catch (ConnectionException $exception) {
            throw new RuntimeException(sprintf(
                'Could not reach Ollama at %s: %s.',
                $this->url(),
                $exception->getMessage(),
            ));
        }

        if ($response->failed()) {
            $error = $response->json('error');

            throw new RuntimeException(sprintf(
                'Ollama chat request failed (model %s): HTTP %d%s.',
                $this->model(),
                $response->status(),
                is_string($error) && $error !== '' ? ' — '.$error : '',
            ));
        }

        $content = $response->json('message.content');

        if (! is_string($content) || $content === '') {
            throw new RuntimeException('Ollama returned an invalid response.');
        }

        return $content;
    }

    private function url(): string
    {
        return (string) config('services.ollama.url', 'http://127.0.0.1:11434');
    }

    private function model(): string
    {
        return (string) config('services.ollama.model', 'llama3.2');
    }
}
