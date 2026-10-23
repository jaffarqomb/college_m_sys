<?php

namespace App\Services\Finance;

use App\Enums\InvoiceStatus;
use App\Models\AcademicTerm;
use App\Models\FeeStructure;
use App\Models\Invoice;
use App\Models\Program;
use App\Models\Student;
use App\Notifications\InvoicePublished;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Invoice lifecycle: term-bill generation from fee structures, publishing of
 * drafts and post-payment status re-evaluation.
 */
class InvoiceService
{
    /**
     * Create draft invoices for every student with active enrollments in the
     * term. Idempotent: a student already having an invoice for the term (any
     * status) is skipped. Program-scoped fees apply only to that program's
     * students; null-program fees apply to all.
     *
     * @return list<Invoice>
     */
    public function generateForTerm(AcademicTerm $term): array
    {
        $grouped = [];

        foreach (FeeStructure::query()
            ->where('term_id', $term->id)
            ->where('is_active', true)
            ->get() as $fee) {
            $grouped[$fee->program_id === null ? 'all' : (string) $fee->program_id][] = $fee;
        }

        $fees = collect($grouped);

        if ($fees->isEmpty()) {
            return [];
        }

        $students = Student::query()
            ->whereHas('enrollments', fn ($query) => $query
                ->where('status', 'active')
                ->whereIn('section_id', $term->courseSections()->select('id')))
            ->with('program')
            ->get();

        return DB::transaction(function () use ($term, $students, $fees): array {
            $created = [];

            foreach ($students as $student) {
                $invoiceFees = $this->applicableFees($fees, $student);

                if ($invoiceFees->isEmpty()) {
                    continue;
                }

                $exists = Invoice::query()
                    ->where('student_id', $student->id)
                    ->where('term_id', $term->id)
                    ->exists();

                if ($exists) {
                    continue;
                }

                $created[] = $this->createInvoice($term, $student, $invoiceFees);
            }

            return $created;
        });
    }

    /**
     * Move a draft invoice to unpaid so it becomes visible and payable.
     */
    public function publish(Invoice $invoice): Invoice
    {
        if ($invoice->status !== InvoiceStatus::Draft->value) {
            return $invoice;
        }

        $invoice->update(['status' => InvoiceStatus::Unpaid->value]);

        $invoice->student->user->notify(new InvoicePublished($invoice->refresh()));

        return $invoice->refresh();
    }

    /**
     * Re-evaluate the live status of an invoice against its payments and due
     * date: paid when the balance is zero, otherwise overdue past the due
     * date, otherwise partial/unpaid depending on any payments received.
     */
    public function recomputeStatus(Invoice $invoice): Invoice
    {
        $status = InvoiceStatus::tryFrom($invoice->status);

        if ($status === null || ! $status->isLive()) {
            return $invoice;
        }

        $balance = $invoice->balance();

        if ($balance <= 0.0) {
            $next = InvoiceStatus::Paid;
        } elseif ($invoice->due_date->isPast()) {
            $next = InvoiceStatus::Overdue;
        } elseif ($invoice->paidTotal() > 0.0) {
            $next = InvoiceStatus::Partial;
        } else {
            $next = InvoiceStatus::Unpaid;
        }

        if ($next !== $status) {
            $invoice->update(['status' => $next->value]);
        }

        return $invoice->refresh();
    }

    /**
     * Flip every live invoice whose due date has passed to overdue.
     *
     * @return int affected rows
     */
    public function markOverdue(): int
    {
        $affected = 0;

        Invoice::query()
            ->whereIn('status', [InvoiceStatus::Unpaid->value, InvoiceStatus::Partial->value])
            ->where('due_date', '<', now()->toDateString())
            ->chunkById(200, function ($invoices) use (&$affected): void {
                foreach ($invoices as $invoice) {
                    $this->recomputeStatus($invoice);
                    $affected++;
                }
            });

        return $affected;
    }

    /**
     * Create one draft invoice with an item per applicable fee.
     *
     * @param  Collection<int, FeeStructure>  $fees
     */
    private function createInvoice(AcademicTerm $term, Student $student, Collection $fees): Invoice
    {
        $invoice = new Invoice([
            'student_id' => $student->id,
            'term_id' => $term->id,
            'number' => $this->nextNumber(),
            'issue_date' => now()->toDateString(),
            'due_date' => now()->addDays(30)->toDateString(),
            'total' => $fees->sum('amount'),
            'status' => InvoiceStatus::Draft->value,
        ]);

        $invoice->save();

        $fees->each(fn (FeeStructure $fee) => $invoice->items()->create([
            'description' => $fee->name,
            'amount' => $fee->amount,
        ]));

        return $invoice;
    }

    /**
     * @param  Collection<int|string, non-empty-list<FeeStructure>>  $fees
     * @return Collection<int, FeeStructure>
     */
    private function applicableFees(Collection $fees, Student $student): Collection
    {
        $programCode = $student->program?->id !== null ? (string) $student->program->id : null;

        $applicable = collect();

        $allFees = $fees->get('all');

        if ($allFees !== null) {
            $applicable = $applicable->concat($allFees);
        }

        if ($programCode !== null) {
            $programFees = $fees->get($programCode);

            if ($programFees !== null) {
                $applicable = $applicable->concat($programFees);
            }
        }

        return $applicable->values();
    }

    /**
     * Sequentially unique invoice number: INV-{Y}{m}-{count of this month+1},
     * retried inside the same DB handle if a race produces a duplicate.
     */
    public function nextNumber(): string
    {
        return DB::transaction(function (): string {
            for ($attempt = 0; $attempt < 3; $attempt++) {
                $sequence = (int) Invoice::query()->where('number', 'like', 'INV-%')->count() + 1;

                $number = sprintf('INV-%s-%d', now()->format('Ym'), $sequence);

                if (! Invoice::query()->where('number', $number)->exists()) {
                    return $number;
                }
            }

            throw new \RuntimeException('Unable to generate a unique invoice number.');
        });
    }
}
