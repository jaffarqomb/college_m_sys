<?php

namespace App\Policies;

/**
 * Invoice line items inherit the invoice's finance authorization; items are
 * managed from the invoice screens rather than as a standalone resource.
 */
class InvoiceItemPolicy extends FinanceResourcePolicy {}
