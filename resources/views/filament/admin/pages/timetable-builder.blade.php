<x-filament-panels::page>
    @if ($this->aiAnalysis !== null)
        <div
            class="fi-timetable-ai-advice overflow-hidden rounded-xl border border-primary-500/40 bg-primary-500/5 dark:border-primary-400/30 dark:bg-primary-400/5"
        >
            <div class="flex items-start justify-between gap-3 border-b border-primary-500/40 px-4 py-3 dark:border-primary-400/30">
                <div class="flex items-center gap-2">
                    <x-filament::icon
                        icon="heroicon-o-sparkles"
                        class="h-5 w-5 text-primary-500 dark:text-primary-400"
                    />
                    <p class="text-sm font-semibold text-primary-600 dark:text-primary-300">
                        {{ $this->aiAnalysisKind === 'conflicts' ? 'AI conflict-fix suggestions' : 'AI draft review' }}
                        <span class="font-normal text-gray-500 dark:text-gray-400">— local model via Ollama ({{ config('services.ollama.model') }}); advisory only, every move must still pass solver validation</span>
                    </p>
                </div>

                <button
                    wire:click="$set('aiAnalysis', null)"
                    type="button"
                    aria-label="Dismiss AI advice"
                    class="text-gray-400 transition hover:text-gray-600 dark:hover:text-gray-300"
                >
                    <x-filament::icon icon="heroicon-o-x-mark" class="h-4 w-4" />
                </button>
            </div>

            <div class="space-y-1.5 px-4 py-3 text-sm text-gray-700 dark:text-gray-300">
                @foreach (preg_split('/\R\R?/', (string) $this->aiAnalysis) as $line)
                    @if (trim((string) $line) !== '')
                        <p class="whitespace-pre-wrap">{{ trim((string) $line) }}</p>
                    @endif
                @endforeach
            </div>
        </div>
    @endif

    {{ $this->content }}
</x-filament-panels::page>
