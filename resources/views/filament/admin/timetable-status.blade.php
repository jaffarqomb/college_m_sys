@php
    use App\Enums\TimetableGenerationStatus as Status;

    $term ??= null;
    $generation ??= null;
@endphp

<div class="fi-timetable-status divide-y divide-gray-200 dark:divide-white/10">
    @if (! isset($generation) || $generation === null)
        <x-filament::callout
            icon="heroicon-o-information-circle"
            color="gray"
            heading="{{ $term?->name ?? 'No term selected' }} — AI timetable"
            description="Generate a draft to preview, adjust and publish this term's weekly section schedule."
        />
    @elseif ($generation->status === Status::Generating)
        <div wire:poll.5s="$wire.$refresh">
            <x-filament::callout
                icon="heroicon-o-clock"
                color="info"
                heading="Generating draft for {{ $generation->term?->name ?? '…' }}…"
                description="The solver is placing this term's sections. This page refreshes automatically every 5 seconds."
            />
        </div>
    @elseif ($generation->status === Status::Failed)
        <x-filament::callout
            icon="heroicon-o-exclamation-triangle"
            color="danger"
            heading="Last generation failed"
            description="Something went wrong while solving {{ $generation->term?->name ?? 'this term' }}. Generate a new draft to try again."
        />
    @else
        @php
            $draftDescription = count($generation->assignments).' section(s) placed';

            if ($generation->fitness !== null) {
                $draftDescription .= ' · utilization & compactness score '.number_format((float) $generation->fitness, 2);
            }
        @endphp

        @if ($generation->status === Status::Draft)
            <x-filament::callout
                icon="heroicon-o-document-chart-bar"
                color="primary"
                heading="Draft for {{ $generation->term?->name ?? 'term' }}"
                :description="$draftDescription"
            />
        @else
            <x-filament::callout
                icon="heroicon-o-check-badge"
                color="success"
                heading="Published for {{ $generation->term?->name ?? 'term' }}"
                description="{{ count($generation->assignments) }} section(s) scheduled · published {{ optional($generation->published_at)->format('M j, Y H:i') }}"
            />
        @endif

        @if (count($generation->conflicts) > 0)
            <x-filament::callout
                icon="heroicon-o-exclamation-triangle"
                color="warning"
                heading="{{ count($generation->conflicts) }} section(s) could not be scheduled"
            >
                <x-slot:footer>
                    @foreach ($generation->conflicts as $conflict)
                        <p class="text-sm text-warning-800 dark:text-warning-300">
                            {{ $conflict['section'] ?? 'Section' }} — {{ $conflict['reason'] ?? 'could not be scheduled' }}
                        </p>
                    @endforeach
                </x-slot:footer>
            </x-filament::callout>
        @endif

        @if ($generation->status === Status::Published)
            <p class="px-4 py-2 text-sm text-gray-500 dark:text-gray-400">
                This draft was published and is now read-only. Generate a new draft to make changes.
            </p>
        @endif
    @endif
</div>
