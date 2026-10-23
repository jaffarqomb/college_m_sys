<?php

namespace App\Actions\Finance;

use App\Models\Invoice;
use App\Services\Finance\InvoiceService;
use Illuminate\Validation\ValidationException;

/**
 * Releases a draft invoice so the student can see and settle it.
 */
class PublishInvoice
{
    public function __construct(private readonly InvoiceService $invoices) {}

    public function handle(Invoice $invoice): Invoice
    {
        if ($invoice->status !== 'draft') {
            throw ValidationException::withMessages([
                'status' => 'Only draft invoices can be published.',
            ]);
        }

        return $this->invoices->publish($invoice);
    }
}
