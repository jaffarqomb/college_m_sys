<?php

use App\Actions\Finance\SettleTapPayment;
use App\Actions\Finance\StartTapCheckout;
use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\User;
use App\Services\Finance\InvoiceService;
use App\Services\Finance\TapGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

const TAP_SECRET = 'sk_test_finance_secret';

/**
 * Deterministic gateway pointed at a fake URL.
 */
function tapGateway(): TapGateway
{
    return new TapGateway(TAP_SECRET, 'https://gateway.test/v2', 'KWD');
}

/**
 * Sign a charge payload the way Tap signs webhook posts.
 *
 * @param  array<string, mixed>  $charge
 */
function tapSigned(array $charge, string $secret = TAP_SECRET): string
{
    $gateway = new TapGateway($secret, 'https://gateway.test/v2', 'KWD');

    $toHash = 'x_id'.($charge['id'] ?? '')
        .'x_amount'.$gateway->roundCurrencyAmount((float) ($charge['amount'] ?? 0), $charge['currency'] ?? 'KWD')
        .'x_currency'.($charge['currency'] ?? '')
        .'x_gateway_reference'.($charge['reference']['gateway'] ?? '')
        .'x_payment_reference'.($charge['reference']['payment'] ?? '')
        .'x_status'.($charge['status'] ?? '')
        .'x_created'.($charge['transaction']['created'] ?? '');

    return hash_hmac('sha256', $toHash, $secret);
}

test('a configured gateway reports its currency and configuration', function () {
    expect(tapGateway()->isConfigured())->toBeTrue()
        ->and(tapGateway()->currency())->toBe('KWD')
        ->and(new TapGateway(null)->isConfigured())->toBeFalse();
});

test('amounts round to their currency ISO decimals', function () {
    $gateway = tapGateway();

    expect($gateway->roundCurrencyAmount(100.4564, 'KWD'))->toBe('100.456')
        ->and($gateway->roundCurrencyAmount(100.4564, 'USD'))->toBe('100.46')
        ->and($gateway->roundCurrencyAmount(100.4564, 'BHD'))->toBe('100.456')
        ->and($gateway->roundCurrencyAmount(100.45, 'JOD'))->toBe('100.450');
});

test('creating a checkout posts the charge and returns the hosted page', function () {
    Http::fake([
        'https://gateway.test/v2/charges' => Http::response([
            'id' => 'chg_123',
            'transaction' => ['url' => 'https://gateway.test/checkout/chg_123'],
        ]),
    ]);

    $payer = User::factory()->create(['name' => 'John Mwangi']);

    $invoice = Invoice::factory()->state(fn () => ['total' => 3200])->create();

    $checkout = tapGateway()->createCheckout($invoice, $payer);

    expect($checkout)->toBe(['id' => 'chg_123', 'redirect_url' => 'https://gateway.test/checkout/chg_123']);

    Http::assertSent(function (Request $request): bool {
        $payload = $request->data();

        return $request->hasHeader('Authorization', 'Bearer '.TAP_SECRET)
            && $payload['amount'] === 3200.0
            && $payload['currency'] === 'KWD'
            && $payload['source']['id'] === 'src_all'
            && $payload['customer']['first_name'] === 'John'
            && $payload['customer']['last_name'] === 'Mwangi'
            && $payload['metadata']['invoice_number'] !== null
            && str_contains((string) $payload['redirect']['url'], '/payments/tap/return')
            && str_contains((string) $payload['post']['url'], '/payments/tap/webhook');
    });
});

test('an unconfigured gateway refuses to create a checkout', function () {
    (new TapGateway(null))->createCheckout(Invoice::factory()->create(), User::factory()->create());
})->throws(RuntimeException::class, 'Tap is not configured');

test('retrieving a charge maps the final state fields', function () {
    Http::fake([
        'https://gateway.test/v2/charges/chg_123' => Http::response([
            'id' => 'chg_123',
            'status' => 'CAPTURED',
            'amount' => 1250.456,
            'currency' => 'KWD',
            'reference' => ['gateway' => 'gw_ref_1', 'payment' => 'pay_ref_1'],
            'transaction' => ['created' => '2026-10-23T09:43:00Z'],
        ]),
    ]);

    $charge = tapGateway()->retrieveCharge('chg_123');

    expect($charge['status'])->toBe('CAPTURED')
        ->and($charge['amount'])->toBe(1250.456)
        ->and($charge['currency'])->toBe('KWD')
        ->and($charge['gateway_reference'])->toBe('gw_ref_1')
        ->and($charge['payment_reference'])->toBe('pay_ref_1');
});

test('valid hashstrings verify and tampered or missing ones do not', function () {
    $charge = [
        'id' => 'chg_123',
        'status' => 'CAPTURED',
        'amount' => 1200.5,
        'currency' => 'KWD',
        'reference' => ['gateway' => 'gw1', 'payment' => 'pay1'],
        'transaction' => ['created' => '2026-10-23T09:43:00Z'],
    ];

    expect(tapGateway()->verifyHashstring($charge, tapSigned($charge)))->toBeTrue()
        ->and(tapGateway()->verifyHashstring($charge, tapSigned($charge, 'sk_other')))->toBeFalse()
        ->and(tapGateway()->verifyHashstring($charge, null))->toBeFalse()
        ->and(tapGateway()->verifyHashstring($charge, ''))->toBeFalse();

    $tampered = array_merge($charge, ['status' => 'FAILED']);

    expect(tapGateway()->verifyHashstring($tampered, tapSigned($charge)))->toBeFalse();
});

test('starting a checkout creates a pending payment keyed by the charge id', function () {
    config()->set('services.tap.secret_key', TAP_SECRET);
    config()->set('services.tap.base_url', 'https://gateway.test/v2');

    Http::fake([
        'https://gateway.test/v2/charges' => Http::response([
            'id' => 'chg_start_1',
            'transaction' => ['url' => 'https://gateway.test/checkout/start'],
        ]),
    ]);

    config()->set('services.tap.secret_key', TAP_SECRET);

    $invoice = Invoice::factory()->create();

    $checkout = app(StartTapCheckout::class)->handle($invoice, User::factory()->create());

    expect($checkout['payment']->status)->toBe(PaymentStatus::Pending->value)
        ->and($checkout['payment']->method)->toBe(PaymentMethod::Tap->value)
        ->and($checkout['payment']->reference)->toBe('chg_start_1')
        ->and($checkout['payment']->invoice->is($invoice))->toBeTrue();
});

test('starting a checkout without gateway configuration throws', function () {
    config()->set('services.tap.secret_key', null);

    app(StartTapCheckout::class)->handle(Invoice::factory()->create(), User::factory()->create());
})->throws(RuntimeException::class, 'Online payments are not configured');

test('unpublished invoices cannot start a checkout', function () {
    config()->set('services.tap.secret_key', TAP_SECRET);

    app(StartTapCheckout::class)->handle(
        Invoice::factory()->state(fn () => ['status' => InvoiceStatus::Draft->value])->create(),
        User::factory()->create(),
    );
})->throws(RuntimeException::class, 'not payable');

test('invoices with a running payment attempt cannot start a second one', function () {
    config()->set('services.tap.secret_key', TAP_SECRET);

    $invoice = Invoice::factory()->create();

    Payment::query()->create([
        'invoice_id' => $invoice->id,
        'amount' => $invoice->balance(),
        'method' => PaymentMethod::Tap->value,
        'reference' => 'chg_running',
        'status' => PaymentStatus::Pending->value,
    ]);

    app(StartTapCheckout::class)->handle($invoice, User::factory()->create());
})->throws(RuntimeException::class, 'payment attempt is already running');

test('settling a captured charge succeeds and pays the invoice', function () {
    $invoice = Invoice::factory()->create();

    $payment = Payment::query()->create([
        'invoice_id' => $invoice->id,
        'amount' => $invoice->balance(),
        'method' => PaymentMethod::Tap->value,
        'reference' => 'chg_settle_1',
        'status' => PaymentStatus::Pending->value,
    ]);

    $settle = new SettleTapPayment(tapGateway(), app(InvoiceService::class));

    $settled = $settle->handle('chg_settle_1', [
        'id' => 'chg_settle_1',
        'status' => 'CAPTURED',
        'amount' => (float) $invoice->total,
        'currency' => 'KWD',
        'reference' => [],
        'transaction' => [],
    ]);

    expect($settled->status)->toBe(PaymentStatus::Succeeded->value)
        ->and($settled->paid_at)->not->toBeNull()
        ->and($invoice->refresh()->status)->toBe(InvoiceStatus::Paid->value);
});

test('settling without a payload retrieves the charge from the gateway', function () {
    Http::fake([
        'https://gateway.test/v2/charges/chg_settle_2' => Http::response([
            'id' => 'chg_settle_2',
            'status' => 'CAPTURED',
            'amount' => 3450.0,
            'currency' => 'KWD',
            'reference' => [],
            'transaction' => [],
        ]),
    ]);

    $invoice = Invoice::factory()->state(fn () => ['total' => 3450])->create();

    Payment::query()->create([
        'invoice_id' => $invoice->id,
        'amount' => $invoice->balance(),
        'method' => PaymentMethod::Tap->value,
        'reference' => 'chg_settle_2',
        'status' => PaymentStatus::Pending->value,
    ]);

    (new SettleTapPayment(tapGateway(), app(InvoiceService::class)))
        ->handle('chg_settle_2');

    expect($invoice->refresh()->status)->toBe(InvoiceStatus::Paid->value);

    Http::assertSent(fn (Request $request): bool => str_contains($request->url(), 'charges/chg_settle_2'));
});

test('non-final charge states leave the payment pending', function () {
    $invoice = Invoice::factory()->create();

    $payment = Payment::query()->create([
        'invoice_id' => $invoice->id,
        'amount' => $invoice->balance(),
        'method' => PaymentMethod::Tap->value,
        'reference' => 'chg_init',
        'status' => PaymentStatus::Pending->value,
    ]);

    (new SettleTapPayment(tapGateway(), app(InvoiceService::class)))
        ->handle('chg_init', [
            'id' => 'chg_init',
            'status' => 'INITIATED',
            'amount' => (float) $invoice->total,
            'currency' => 'KWD',
            'reference' => [],
            'transaction' => [],
        ]);

    expect($payment->refresh()->status)->toBe(PaymentStatus::Pending->value)
        ->and($invoice->refresh()->status)->toBe(InvoiceStatus::Unpaid->value);
});

test('charges for less than the recorded amount are marked failed for review', function () {
    $invoice = Invoice::factory()->create();

    $payment = Payment::query()->create([
        'invoice_id' => $invoice->id,
        'amount' => $invoice->balance(),
        'method' => PaymentMethod::Tap->value,
        'reference' => 'chg_short',
        'status' => PaymentStatus::Pending->value,
    ]);

    (new SettleTapPayment(tapGateway(), app(InvoiceService::class)))
        ->handle('chg_short', [
            'id' => 'chg_short',
            'status' => 'CAPTURED',
            'amount' => 100.0,
            'currency' => 'KWD',
            'reference' => [],
            'transaction' => [],
        ]);

    expect($payment->refresh()->status)->toBe(PaymentStatus::Failed->value)
        ->and($invoice->refresh()->status)->toBe(InvoiceStatus::Unpaid->value);
});

test('settling is idempotent — later replays never double-apply', function () {
    $invoice = Invoice::factory()->create();

    $payment = Payment::query()->create([
        'invoice_id' => $invoice->id,
        'amount' => $invoice->balance(),
        'method' => PaymentMethod::Tap->value,
        'reference' => 'chg_replay',
        'status' => PaymentStatus::Pending->value,
    ]);

    $charge = [
        'id' => 'chg_replay',
        'status' => 'CAPTURED',
        'amount' => (float) $invoice->total,
        'currency' => 'KWD',
        'reference' => [],
        'transaction' => [],
    ];

    $settle = new SettleTapPayment(tapGateway(), app(InvoiceService::class));

    $settle->handle('chg_replay', $charge);
    $again = $settle->handle('chg_replay', $charge);

    expect($again->status)->toBe(PaymentStatus::Succeeded->value)
        ->and($payment->refresh()->status)->toBe(PaymentStatus::Succeeded->value)
        ->and(Payment::query()->where('reference', 'chg_replay')->count())->toBe(1)
        ->and($invoice->refresh()->paidTotal())->toBe((float) $invoice->total);
});

test('unknown charges cannot silently vanish pending payments', function () {
    $invoice = Invoice::factory()->create();

    $payment = Payment::query()->create([
        'invoice_id' => $invoice->id,
        'amount' => $invoice->balance(),
        'method' => PaymentMethod::Tap->value,
        'reference' => 'chg_missing',
        'status' => PaymentStatus::Pending->value,
    ]);

    (new SettleTapPayment(tapGateway(), app(InvoiceService::class)))
        ->handleUnknownCharge('chg_missing', 'Gateway reported an unknown charge.');

    expect($payment->refresh()->status)->toBe(PaymentStatus::Failed->value);
});
