<?php

use App\Actions\Finance\RecordManualPayment;
use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Invoice;
use App\Models\Payment;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('recording a manual payment creates a succeeded payment and recomputes the invoice', function () {
    $invoice = Invoice::factory()->create();

    $payment = app(RecordManualPayment::class)->handle($invoice, 500, 'CASH-1', 'Counter');

    expect($payment->status)->toBe(PaymentStatus::Succeeded->value)
        ->and($payment->method)->toBe(PaymentMethod::Manual->value)
        ->and($payment->paid_at)->not->toBeNull()
        ->and($invoice->refresh()->status)->toBe(InvoiceStatus::Partial->value)
        ->and($invoice->balance())->toBe((float) $invoice->total - 500.0);

    $this->assertDatabaseHas(Payment::class, [
        'invoice_id' => $invoice->id,
        'reference' => 'CASH-1',
        'notes' => 'Counter',
        'status' => PaymentStatus::Succeeded->value,
    ]);
});

test('paying the full balance moves the invoice to paid', function () {
    $invoice = Invoice::factory()->create();

    app(RecordManualPayment::class)->handle($invoice, (float) $invoice->total);

    expect($invoice->refresh()->status)->toBe(InvoiceStatus::Paid->value);
});

test('recording a payment against an unpublished draft is rejected', function () {
    $invoice = Invoice::factory()->state(fn () => ['status' => 'draft'])->create();

    app(RecordManualPayment::class)->handle($invoice, 100);
})->throws('Payments can only be recorded against published invoices.');

test('recording a payment against a settled invoice is rejected', function () {
    $invoice = Invoice::factory()->paid()->create();

    app(RecordManualPayment::class)->handle($invoice, 100);
})->throws('Payments can only be recorded against published invoices.');

test('zero and negative amounts are rejected', function (float $amount) {
    $invoice = Invoice::factory()->create();

    app(RecordManualPayment::class)->handle($invoice, $amount);
})->with([
    0.0,
    -5.0,
])->throws('The payment amount must be positive.');

test('overpaying the balance is rejected', function () {
    $invoice = Invoice::factory()->create();

    app(RecordManualPayment::class)->handle($invoice, (float) $invoice->total + 10);
})->throws('The payment exceeds the outstanding balance of');
