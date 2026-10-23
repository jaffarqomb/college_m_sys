<?php

use App\Enums\InvoiceStatus;
use App\Enums\PaymentStatus;
use App\Models\Invoice;
use App\Models\Payment;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\post;

uses(RefreshDatabase::class);

const TAP_HOOK_SECRET = 'sk_test_hook_secret';

/**
 * An invoice with a pending Tap payment awaiting settlement.
 *
 * @return array{invoice: Invoice, payment: Payment}
 */
function tapHookPending(): array
{
    config()->set('services.tap.secret_key', TAP_HOOK_SECRET);

    $invoice = Invoice::factory()->state(fn () => ['total' => 1500])->create();

    $payment = Payment::query()->create([
        'invoice_id' => $invoice->id,
        'amount' => $invoice->balance(),
        'method' => 'tap',
        'reference' => 'chg_hook_1',
        'status' => PaymentStatus::Pending->value,
    ]);

    return ['invoice' => $invoice, 'payment' => $payment];
}

/**
 * A captured charge matching the pending payment, un-signed.
 *
 * @return array<string, mixed>
 */
function tapHookCharge(array $overrides = []): array
{
    return array_merge([
        'id' => 'chg_hook_1',
        'status' => 'CAPTURED',
        'amount' => 1500,
        'currency' => 'KWD',
        'reference' => ['gateway' => 'gw_9', 'payment' => 'pay_9'],
        'transaction' => ['created' => '2026-10-23T10:00:00Z'],
    ], $overrides);
}

/**
 * Sign a charge payload the way Tap signs webhook posts.
 *
 * @param  array<string, mixed>  $charge
 */
function tapHookHash(array $charge, string $secret = TAP_HOOK_SECRET): string
{
    $amount = number_format(round((float) ($charge['amount'] ?? 0), 3), 3, '.', '');

    $toHash = 'x_id'.($charge['id'] ?? '')
        .'x_amount'.$amount
        .'x_currency'.($charge['currency'] ?? '')
        .'x_gateway_reference'.($charge['reference']['gateway'] ?? '')
        .'x_payment_reference'.($charge['reference']['payment'] ?? '')
        .'x_status'.($charge['status'] ?? '')
        .'x_created'.($charge['transaction']['created'] ?? '');

    return hash_hmac('sha256', $toHash, $secret);
}

test('a correctly signed captured webhook settles the payment and pays the invoice', function () {
    ['invoice' => $invoice, 'payment' => $payment] = tapHookPending();

    $charge = tapHookCharge();

    post('/payments/tap/webhook', $charge, ['hashstring' => tapHookHash($charge)])
        ->assertOk()
        ->assertJsonPath('settled', PaymentStatus::Succeeded->value);

    expect($payment->refresh()->status)->toBe(PaymentStatus::Succeeded->value)
        ->and($invoice->refresh()->status)->toBe(InvoiceStatus::Paid->value);
});

test('replaying the same webhook never settles twice', function () {
    ['invoice' => $invoice] = tapHookPending();

    $charge = tapHookCharge();
    $headers = ['hashstring' => tapHookHash($charge)];

    post('/payments/tap/webhook', $charge, $headers)->assertOk();
    post('/payments/tap/webhook', $charge, $headers)->assertOk();

    expect(Payment::query()->where('reference', 'chg_hook_1')->where('status', 'succeeded')->count())->toBe(1)
        ->and($invoice->refresh()->paidTotal())->toBe(1500.0)
        ->and($invoice->status)->toBe(InvoiceStatus::Paid->value);
});

test('webhook posts with a tampered, bad or missing hashstring are rejected', function () {
    tapHookPending();

    $charge = tapHookCharge();
    $tampered = $charge;
    $tampered['status'] = 'FAILED';

    post('/payments/tap/webhook', $charge, ['hashstring' => tapHookHash($tampered)])
        ->assertStatus(403);

    post('/payments/tap/webhook', $charge, ['hashstring' => 'deadbeef'])
        ->assertStatus(403);

    post('/payments/tap/webhook', $charge)
        ->assertStatus(403);

    expect(Payment::query()->where('reference', 'chg_hook_1')->first()->status)
        ->toBe('pending');
});

test('webhook posts for unknown charges are accepted harmlessly', function () {
    tapHookPending();

    $charge = tapHookCharge(['id' => 'chg_unknown']);

    post('/payments/tap/webhook', $charge, ['hashstring' => tapHookHash($charge)])
        ->assertOk()
        ->assertJsonPath('settled', null);
});

test('webhook posts below the recorded amount are investigated, not settled', function () {
    ['invoice' => $invoice, 'payment' => $payment] = tapHookPending();

    $charge = tapHookCharge(['amount' => 100.0]);

    post('/payments/tap/webhook', $charge, ['hashstring' => tapHookHash($charge)])
        ->assertOk()
        ->assertJsonPath('settled', PaymentStatus::Failed->value);

    expect($payment->refresh()->status)->toBe(PaymentStatus::Failed->value)
        ->and($invoice->refresh()->status)->toBe(InvoiceStatus::Unpaid->value);
});

test('empty webhook payloads are rejected', function () {
    config()->set('services.tap.secret_key', TAP_HOOK_SECRET);

    post('/payments/tap/webhook', [])->assertStatus(400);
});
