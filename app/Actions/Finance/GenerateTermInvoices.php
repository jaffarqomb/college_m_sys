<?php

namespace App\Actions\Finance;

use App\Models\AcademicTerm;
use App\Models\Invoice;
use App\Services\Finance\InvoiceService;
use Illuminate\Validation\ValidationException;

/**
 * Generates the term billing run from the active fee structures.
 */
class GenerateTermInvoices
{
    public function __construct(private readonly InvoiceService $invoices) {}

    /**
     * @return list<Invoice>
     */
    public function handle(AcademicTerm $term): array
    {
        $created = $this->invoices->generateForTerm($term);

        if ($created === []) {
            throw ValidationException::withMessages([
                'fees' => 'No invoices were generated. Add active fee structures for this term first.',
            ]);
        }

        return $created;
    }
}
