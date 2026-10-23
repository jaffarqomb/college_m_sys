<?php

namespace App\Actions\Finance;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Notifications\PaymentRecorded;
use App\Services\Finance\InvoiceService;
use App\Services\Finance\TapGateway;
use Illuminate\Validation\ValidationException;

/**
 * Settles a Tap charge against its pending payment row. Called from the
 * redirect-return route and the webhook; safe to run repeatedly — already
 * settled payments short-circuit and the merchant charge is verified against
 * the recorded amount before any money state changes.
 */
class SettleTapPayment
{
    public function __construct(
        private readonly TapGateway $gateway,
        private readonly InvoiceService $invoices,
    ) {}

    /**
     * @param  array<string, mixed>|null  $charge
     */
    public function handle(string $chargeId, ?array $charge = null): ?Payment
    {
        $payment = Payment::query()
            ->where('method', PaymentMethod::Tap->value)
            ->where('reference', $chargeId)
            ->first();

        if ($payment === null) {
            return null;
        }

        if ($payment->status !== PaymentStatus::Pending->value) {
            return $payment; // Already handled (idempotent replay).
        }

        $charge ??= $this->gateway->retrieveCharge($chargeId);

        if ((string) $charge['currency'] !== $this->gateway->currency()) {
            throw ValidationException::withMessages([
                'charge' => 'Unexpected charge currency from the gateway.',
            ]);
        }

        if ((float) $charge['amount'] + 0.001 < (float) $payment->amount) {
            $this->markFailed($payment, 'Gateway charge amount is lower than the invoice balance.');

            return $payment;
        }

        if (strtoupper($charge['status']) !== 'CAPTURED') {
            // Not final yet (AUTHORIZE flows, initiations etc.). Leave pending;
            // the webhook will return with the terminal status.
            return $payment;
        }

        $payment->update([
            'status' => PaymentStatus::Succeeded->value,
            'paid_at' => now(),
            'notes' => 'Tap charge captured',
        ]);

        $this->invoices->recomputeStatus($payment->invoice);

        $payment->invoice->student->user->notify(new PaymentRecorded($payment->refresh()));

        $this->gateway->log('Charge settled', [
            'charge_id' => $chargeId,
            'payment_id' => $payment->id,
        ]);

        return $payment->refresh();
    }

    /**
     * Handle a webhook/redirect for an unknown or suspicious charge payload:
     * mark the recorded pending payment failed (never unmark a settled one).
     */
    public function handleUnknownCharge(string $chargeId, string $reason): void
    {
        $payment = Payment::query()
            ->where('method', PaymentMethod::Tap->value)
            ->where('reference', $chargeId)
            ->where('status', PaymentStatus::Pending->value)
            ->first();

        if ($payment !== null) {
            $this->markFailed($payment, $reason);
        }
    }

    private function markFailed(Payment $payment, string $reason): void
    {
        $payment->update([
            'status' => PaymentStatus::Failed->value,
            'notes' => $reason,
        ]);

        $this->gateway->log('Charge not settled', [
            'charge_id' => $payment->reference,
            'payment_id' => $payment->id,
            'reason' => $reason,
        ]);
    }
}
