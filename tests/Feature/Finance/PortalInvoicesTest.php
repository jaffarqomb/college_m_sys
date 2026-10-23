<?php

use App\Enums\InvoiceStatus;
use App\Enums\PaymentStatus;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

/**
 * A signed-in student (with personal team) whose dashboard can be viewed.
 *
 * @return array{user: User, student: Student}
 */
function finStudent(): array
{
    $student = Student::factory()->create();

    return ['user' => $student->user, 'student' => $student];
}

function finDashboardUrl(User $user): string
{
    return route('dashboard', ['current_team' => $user->currentTeam->slug]);
}

test('the invoices island lists published invoices with balances', function () {
    ['user' => $user, 'student' => $student] = finStudent();

    $unpaid = Invoice::factory()->state(fn () => ['student_id' => $student->id, 'total' => 3450])->create();
    $draft = Invoice::factory()->state(fn () => ['student_id' => $student->id, 'status' => 'draft'])->create();
    $other = Invoice::factory()->create();

    actingAs($user)
        ->get(finDashboardUrl($user))
        ->assertOk()
        ->assertSee($unpaid->number)
        ->assertSee('3450')
        ->assertDontSee($draft->number)
        ->assertDontSee($other->number);
});

test('without gateway configuration the island shows the manual payment note', function () {
    ['user' => $user, 'student' => $student] = finStudent();

    config()->set('services.tap.secret_key', null);

    $invoice = Invoice::factory()->state(fn () => ['student_id' => $student->id])->create();

    actingAs($user)
        ->get(finDashboardUrl($user))
        ->assertOk()
        ->assertSee($invoice->number)
        ->assertDontSee('Pay now')
        ->assertSee('manual-payment-note', false);
});

test('with gateway configuration each balance-carrying invoice offers a pay-now link', function () {
    ['user' => $user, 'student' => $student] = finStudent();

    config()->set('services.tap.secret_key', 'sk_test_portal');

    $invoice = Invoice::factory()->state(fn () => ['student_id' => $student->id, 'total' => 1000])->create();
    $settled = Invoice::factory()->paid()->state(fn () => ['student_id' => $student->id])->create();

    actingAs($user)
        ->get(finDashboardUrl($user))
        ->assertOk()
        ->assertSee('pay-now-'.$invoice->id, false)
        ->assertDontSee('pay-now-'.$settled->id, false);
});

test('a returning payer gets the payment verified straight away', function () {
    ['user' => $user, 'student' => $student] = finStudent();

    config()->set('services.tap.secret_key', 'sk_test_return');

    $invoice = Invoice::factory()->state(fn () => ['student_id' => $student->id, 'total' => 3450])->create();

    $payment = Payment::query()->create([
        'invoice_id' => $invoice->id,
        'amount' => $invoice->balance(),
        'method' => 'tap',
        'reference' => 'chg_return_1',
        'status' => PaymentStatus::Pending->value,
    ]);

    Http::fake([
        '*' => Http::response([
            'id' => 'chg_return_1',
            'status' => 'CAPTURED',
            'amount' => 3450,
            'currency' => 'KWD',
            'reference' => [],
            'transaction' => [],
        ]),
    ]);

    actingAs($user)
        ->get(route('tap.return', ['current_team' => $user->currentTeam->slug, 'tap_id' => 'chg_return_1']))
        ->assertRedirect(finDashboardUrl($user));

    expect($payment->refresh()->status)->toBe(PaymentStatus::Succeeded->value)
        ->and($invoice->refresh()->status)->toBe(InvoiceStatus::Paid->value);
});

test('students cannot pay another student\'s invoice', function () {
    ['user' => $user] = finStudent();

    Http::fake([
        'https://api.tap.company/v2/charges' => Http::response(['id' => 'chg_no', 'transaction' => []]),
    ]);

    $foreign = Invoice::factory()->create();

    actingAs($user)
        ->get(route('invoices.pay', ['current_team' => $user->currentTeam->slug, 'invoice' => $foreign->id]))
        ->assertForbidden();

    expect(Payment::query()->where('invoice_id', $foreign->id)->count())->toBe(0);
});

test('paying an own published invoice starts a checkout and redirects to the hosted page', function () {
    ['user' => $user, 'student' => $student] = finStudent();

    config()->set('services.tap.secret_key', 'sk_test_pay');

    Http::fake([
        'https://api.tap.company/v2/charges' => Http::response([
            'id' => 'chg_pay_1',
            'transaction' => ['url' => 'https://gateway.checkout/pay'],
        ]),
    ]);

    $invoice = Invoice::factory()->state(fn () => ['student_id' => $student->id, 'total' => 2000])->create();

    actingAs($user)
        ->get(route('invoices.pay', ['current_team' => $user->currentTeam->slug, 'invoice' => $invoice->id]))
        ->assertRedirect('https://gateway.checkout/pay');

    expect(Payment::query()->where('invoice_id', $invoice->id)->where('status', 'pending')->count())->toBe(1);

    $checkout = Payment::query()->where('invoice_id', $invoice->id)->first();
    expect($checkout->reference)->toBe('chg_pay_1');
});

test('paying an own draft invoice is refused and surfaces a toast', function () {
    ['user' => $user, 'student' => $student] = finStudent();

    config()->set('services.tap.secret_key', 'sk_test_pay');

    $draft = Invoice::factory()->state(fn () => ['student_id' => $student->id, 'status' => 'draft'])->create();

    $response = actingAs($user)
        ->get(route('invoices.pay', ['current_team' => $user->currentTeam->slug, 'invoice' => $draft->id]));

    $response->assertRedirect();

    expect($response->getSession()->get('toast'))->toContain('could not start');
});
