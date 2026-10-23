<?php

namespace App\Actions\Finance;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\User;
use App\Services\Finance\TapGateway;

/**
 * Starts a Tap hosted-checkout flow for the invoice balance. Creates the
 * pending payment row keyed by the Tap charge id so the return route and the
 * webhook can both settle it later (idempotently).
 */
class StartTapCheckout
{
    public function __construct(private readonly TapGateway $gateway) {}

    /**
     * @return array{payment: Payment, redirect_url: string|null}
     */
    public function handle(Invoice $invoice, User $payer): array
    {
        if (! $this->gateway->isConfigured()) {
            throw new \RuntimeException('Online payments are not configured for this college.');
        }

        TapGateway::assertPayable($invoice);

        $checkout = $this->gateway->createCheckout($invoice, $payer);

        $payment = Payment::query()->create([
            'invoice_id' => $invoice->id,
            'amount' => $invoice->balance(),
            'method' => PaymentMethod::Tap->value,
            'reference' => $checkout['id'],
            'status' => PaymentStatus::Pending->value,
            'notes' => 'Tap checkout started '.now()->toDateTimeString(),
        ]);

        return ['payment' => $payment, 'redirect_url' => $checkout['redirect_url']];
    }
}
