<?php

use App\Actions\Site\ResolveBotReply;
use App\Models\ChatSession;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

new class extends Component {
    /** @var array<int, array{role: string, content: string}> */
    public array $messages = [];

    public string $question = '';

    public bool $open = false;

    public ?int $sessionId = null;

    public function mount(): void
    {
        $visitorToken = session()->get('chatbot_visitor');

        if (! is_string($visitorToken) || $visitorToken === '') {
            $visitorToken = Str::random(32);
            session()->put('chatbot_visitor', $visitorToken);
        }

        $session = ChatSession::query()->firstOrCreate([
            'visitor_token' => $visitorToken,
        ]);

        $this->sessionId = $session->id;
        $this->messages = [
            ['role' => 'bot', 'content' => config('chatbot.messages.greeting')],
        ];
    }

    public function send(ResolveBotReply $resolveBotReply): void
    {
        $question = trim($this->question);

        if ($question === '' || $this->sessionId === null) {
            return;
        }

        $this->question = '';

        $this->messages[] = ['role' => 'user', 'content' => $question];

        try {
            $reply = $resolveBotReply->handle(
                ChatSession::findOrFail($this->sessionId),
                $question,
            );

            $this->messages[] = ['role' => 'bot', 'content' => $reply['reply']];
        } catch (ValidationException $exception) {
            $this->messages[] = [
                'role' => 'bot',
                'content' => $exception->errors()['question'][0] ?? config('chatbot.messages.rate_limited'),
            ];
        }
    }
}; ?>

<div
    class="fixed inset-x-4 bottom-4 z-50 sm:inset-x-auto sm:right-6 sm:bottom-6"
    data-test="site-chatbot"
>
    @if ($open)
        <div
            class="mb-3 w-full sm:w-[380px] rounded-2xl border border-zinc-200 bg-white shadow-xl shadow-zinc-900/10 dark:border-zinc-800 dark:bg-zinc-900 dark:shadow-black/40"
            data-test="chatbot-panel"
        >
        <div class="flex items-center justify-between gap-3 rounded-t-2xl bg-gradient-to-r from-indigo-600 to-violet-600 px-4 py-3">
            <div class="flex items-center gap-3">
                <div class="flex size-9 items-center justify-center rounded-full bg-white/15 text-white">
                    <flux:icon.sparkles class="size-5" />
                </div>
                <div>
                    <p class="text-sm font-semibold text-white">Campus Assistant</p>
                    <p class="text-xs text-indigo-100">Online &bull; replies instantly</p>
                </div>
            </div>

            <button type="button" wire:click="$toggle('open')" class="rounded-md p-1 text-white/80 transition hover:bg-white/10 hover:text-white" aria-label="Close chat">
                <flux:icon.x-mark class="size-4" />
            </button>
        </div>

        <div class="max-h-[420px] min-h-[320px] space-y-3 overflow-y-auto px-4 py-4">
            @foreach ($messages as $index => $message)
                <div class="flex {{ $message['role'] === 'user' ? 'justify-end' : 'justify-start' }}" wire:key="message-{{ $index }}">
                    <p
                        class="max-w-[85%] rounded-2xl px-4 py-2 text-sm leading-relaxed {{ $message['role'] === 'user'
                            ? 'rounded-br-sm bg-indigo-600 text-white'
                            : 'rounded-bl-sm bg-zinc-100 text-zinc-800 dark:bg-zinc-800 dark:text-zinc-100' }}"
                    >
                        {{ $message['content'] }}
                    </p>
                </div>
            @endforeach

            <div class="hidden justify-start" wire:target="send" wire:loading.flex>
                <span class="rounded-2xl rounded-bl-sm bg-zinc-100 px-4 py-2 text-sm text-zinc-500 dark:bg-zinc-800 dark:text-zinc-400">
                    Assistant is typing…
                </span>
            </div>
        </div>

        <form wire:submit="send" class="border-t border-zinc-200 p-3 dark:border-zinc-800">
            <div class="flex items-center gap-2">
                <input
                    type="text"
                    wire:model="question"
                    placeholder="Ask about programs, fees, timetable…"
                    class="h-10 w-full rounded-xl border border-zinc-300 bg-white px-4 text-sm outline-none transition focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20 dark:border-zinc-700 dark:bg-zinc-800 dark:text-white"
                    data-test="chatbot-input"
                />

                <flux:button variant="primary" icon="paper-airplane" type="submit" data-test="chatbot-send" />
            </div>
        </form>
    </div>
    @endif

    <div class="flex justify-end">
        @unless ($open)
            <button
                type="button"
                wire:click="$toggle('open')"
                data-test="chatbot-bubble-open"
                class="flex size-14 items-center justify-center rounded-full bg-gradient-to-br from-indigo-600 to-violet-600 text-white shadow-lg shadow-indigo-600/40 transition hover:scale-105 active:scale-95"
                aria-label="Open chat assistant"
            >
                <flux:icon.chat-bubble-left-right class="size-6" />
            </button>
        @endunless
    </div>
</div>
