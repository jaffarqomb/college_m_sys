<?php

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Services\Finance\TapGateway;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component {
    #[Computed]
    public function invoices(): Collection
    {
        $student = auth()->user()?->studentProfile;

        if ($student === null) {
            return collect();
        }

        return $student->invoices()
            ->where('status', '!=', InvoiceStatus::Draft->value)
            ->with('term', 'items', 'payments')
            ->orderByDesc('due_date')
            ->get();
    }

    #[Computed]
    public function tapConfigured(): bool
    {
        return app(TapGateway::class)->isConfigured();
    }

    public function placeholder(): string
    {
        return <<<'HTML'
            <div class="animate-pulse space-y-3 p-6">
                <div class="h-4 w-1/2 rounded bg-zinc-200 dark:bg-zinc-700"></div>
                <div class="h-16 rounded bg-zinc-200 dark:bg-zinc-700"></div>
                <div class="h-16 rounded bg-zinc-200 dark:bg-zinc-700"></div>
            </div>
            HTML;
    }
}

?>

<div class="rounded-2xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-800 dark:bg-zinc-900" data-test="portal-invoices">
    <div class="border-b border-zinc-200 px-6 py-4 dark:border-zinc-800">
        <h2 class="flex items-center gap-2 text-base font-semibold text-zinc-900 dark:text-zinc-100">
            <flux:icon.banknotes class="size-5 text-indigo-600 dark:text-indigo-400" />
            My invoices
        </h2>
        <p class="text-sm text-zinc-500 dark:text-zinc-400">Tuition and fees for your active term enrollments.</p>
    </div>

    <div class="p-6">
        @forelse ($this->invoices() as $invoice)
            <div class="mb-4 rounded-xl border border-zinc-200 p-4 last:mb-0 dark:border-zinc-800" wire:key="invoice-row-{{ $invoice->id }}" data-test="invoice-row-{{ $invoice->id }}">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <span class="font-medium text-zinc-900 dark:text-zinc-100">{{ $invoice->number }}</span>
                        <span class="block text-xs text-zinc-500 dark:text-zinc-400">
                            {{ $invoice->term->name }} · due {{ $invoice->due_date->format('M j, Y') }}
                        </span>
                    </div>

                    <span class="rounded-full px-2.5 py-1 text-xs font-semibold
                        {{ $invoice->status === InvoiceStatus::Paid->value ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/50 dark:text-emerald-300' : '' }}
                        {{ $invoice->status === InvoiceStatus::Overdue->value ? 'bg-rose-50 text-rose-700 dark:bg-rose-950/50 dark:text-rose-300' : '' }}
                        {{ $invoice->status === InvoiceStatus::Partial->value ? 'bg-amber-50 text-amber-700 dark:bg-amber-950/50 dark:text-amber-300' : '' }}
                        {{ in_array($invoice->status, [InvoiceStatus::Unpaid->value], true) ? 'bg-zinc-100 text-zinc-700 dark:bg-zinc-800 dark:text-zinc-300' : '' }}"
                        data-test="invoice-status-{{ $invoice->id }}">
                        {{ InvoiceStatus::from($invoice->status)->label() }}
                    </span>
                </div>

                <div class="mt-3 flex flex-wrap items-center justify-between gap-3 text-sm">
                    <div class="text-zinc-600 dark:text-zinc-300">
                        <span class="font-medium">{{ (float) $invoice->total }}</span> total
                        @if ((float) $invoice->total > $invoice->balance())
                            · <span class="font-medium" data-test="invoice-balance-{{ $invoice->id }}">{{ $invoice->balance() }}</span> due
                        @endif
                    </div>

                    @if ($invoice->balance() > 0 && $this->tapConfigured() && InvoiceStatus::tryFrom($invoice->status)?->isLive())
                        <a href="{{ route('invoices.pay', ['current_team' => auth()->user()->currentTeam?->slug, 'invoice' => $invoice->id]) }}"
                           class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-3.5 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500"
                           data-test="pay-now-{{ $invoice->id }}">
                            Pay now
                        </a>
                    @endif
                </div>
            </div>
        @empty
            <p class="text-sm text-zinc-500 dark:text-zinc-400">No invoices yet. Invoices appear here once the finance office publishes the term billing.</p>
        @endforelse

        @if ($this->invoices()->isNotEmpty() && ! $this->tapConfigured())
            <div class="mt-4 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-xs text-amber-800 dark:border-amber-900 dark:bg-amber-950/40 dark:text-amber-200" data-test="manual-payment-note">
                Online payments are not enabled. Please settle outstanding balances at the finance office (cash or bank transfer).
            </div>
        @endif
    </div>
</div>
