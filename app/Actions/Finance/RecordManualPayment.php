<?php

namespace App\Actions\Finance;

use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Invoice;
use App\Models\Payment;
use App\Notifications\PaymentRecorded;
use App\Services\Finance\InvoiceService;
use Illuminate\Validation\ValidationException;

/**
 * Records an off-platform payment (cash, bank transfer) as a finance officer.
 */
class RecordManualPayment
{
    public function __construct(private readonly InvoiceService $invoices) {}

    public function handle(Invoice $invoice, float $amount, ?string $reference = null, ?string $notes = null): Payment
    {
        if (! InvoiceStatus::tryFrom($invoice->status)?->isLive()) {
            throw ValidationException::withMessages([
                'invoice' => 'Payments can only be recorded against published invoices.',
            ]);
        }

        $balance = $invoice->balance();

        if ($amount <= 0.0) {
            throw ValidationException::withMessages([
                'amount' => 'The payment amount must be positive.',
            ]);
        }

        if ($amount > $balance + 0.001) {
            throw ValidationException::withMessages([
                'amount' => "The payment exceeds the outstanding balance of {$balance}.",
            ]);
        }

        $payment = Payment::query()->create([
            'invoice_id' => $invoice->id,
            'amount' => $amount,
            'method' => PaymentMethod::Manual->value,
            'reference' => $reference,
            'status' => PaymentStatus::Succeeded->value,
            'paid_at' => now(),
            'notes' => $notes,
        ]);

        $this->invoices->recomputeStatus($invoice);

        $invoice->student->user->notify(new PaymentRecorded($payment->fresh()));

        return $payment;
    }
}
