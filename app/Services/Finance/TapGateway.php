<?php

namespace App\Services\Finance;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Laravel wrapper around the Tap Payments Charge API v2.
 *
 * The hosted-checkout flow is used: creating a charge for "src_all" returns a
 * hosted payment page URL. Once the payer completes it, Tap redirects back to
 * our return route and additionally posts a signed webhook with the final
 * charge payload (validated by the HMAC hashstring).
 *
 * @see https://developers.tap.company/docs/get-started
 */
class TapGateway
{
    public function __construct(
        private readonly ?string $secretKey = null,
        private readonly ?string $baseUrl = null,
        private readonly ?string $currency = null,
    ) {}

    private function secret(): ?string
    {
        return $this->secretKey ?? (config('services.tap.secret_key') ?: null);
    }

    private function baseUrl(): string
    {
        return $this->baseUrl ?? rtrim((string) config('services.tap.base_url', 'https://api.tap.company/v2'), '/');
    }

    /**
     * The currency the college invoices in (Tap is GCC-focused: KWD, SAR...).
     */
    public function currency(): string
    {
        return strtoupper((string) ($this->currency ?? config('services.tap.currency', 'KWD')));
    }

    public function isConfigured(): bool
    {
        return $this->secret() !== null;
    }

    /**
     * Create a hosted-checkout charge for the invoice balance.
     *
     * @return array{id: string, redirect_url: string|null}
     */
    public function createCheckout(Invoice $invoice, User $payer): array
    {
        $secret = $this->secret();

        if ($secret === null) {
            throw new \RuntimeException('Tap is not configured (services.tap.secret_key missing).');
        }

        $nameParts = $this->nameParts($payer->name);

        $response = Http::withToken($secret)
            ->acceptJson()
            ->post($this->baseUrl().'/charges', [
                'amount' => $invoice->balance(),
                'currency' => $this->currency(),
                'customer_initiated' => true,
                'threeDSecure' => true,
                'description' => 'Invoice '.$invoice->number,
                'metadata' => [
                    'invoice_id' => (string) $invoice->id,
                    'invoice_number' => $invoice->number,
                ],
                'reference' => [
                    'transaction' => $invoice->number,
                    'order' => 'INV-'.$invoice->id,
                ],
                'customer' => [
                    'first_name' => $nameParts[0],
                    'last_name' => $nameParts[1],
                    'email' => $payer->email,
                ],
                'source' => [
                    'id' => 'src_all',
                ],
                'redirect' => [
                    'url' => route('tap.return', ['current_team' => $payer->currentTeam?->slug]),
                ],
                'post' => [
                    'url' => route('tap.webhook'),
                ],
            ])->throw()->json();

        if (! is_array($response) || ! isset($response['id'])) {
            throw new \RuntimeException('Tap did not return a charge id.');
        }

        return [
            'id' => (string) $response['id'],
            'redirect_url' => $response['transaction']['url'] ?? null,
        ];
    }

    /**
     * Retrieve the latest state of a charge from Tap.
     *
     * @return array{id: string, status: string, amount: float, currency: string, gateway_reference: string, payment_reference: string, created: string}
     */
    public function retrieveCharge(string $chargeId): array
    {
        $secret = $this->secret();

        if ($secret === null) {
            throw new \RuntimeException('Tap is not configured (services.tap.secret_key missing).');
        }

        $charge = Http::withToken($secret)
            ->acceptJson()
            ->get($this->baseUrl().'/charges/'.$chargeId)
            ->throw()->json();

        return [
            'id' => (string) ($charge['id'] ?? ''),
            'status' => (string) ($charge['status'] ?? ''),
            'amount' => (float) ($charge['amount'] ?? 0),
            'currency' => (string) ($charge['currency'] ?? ''),
            'gateway_reference' => (string) ($charge['reference']['gateway'] ?? ''),
            'payment_reference' => (string) ($charge['reference']['payment'] ?? ''),
            'created' => (string) ($charge['transaction']['created'] ?? ''),
        ];
    }

    /**
     * Validate the hashstring header of a webhook/redirect payload.
     *
     * The concatenated string is defined by Tap:
     * x_id{id}x_amount{amount}x_currency{currency}x_gateway_reference{...}
     * x_payment_reference{...}x_status{status}x_created{created}
     * hashed with HMAC-SHA256 and the secret API key. The amount must be
     * rounded to the currency's ISO decimal places first.
     */
    /**
     * @param  array<string, mixed>  $charge
     */
    public function verifyHashstring(array $charge, ?string $postedHashstring): bool
    {
        $secret = $this->secret();

        if ($secret === null || $postedHashstring === null || $postedHashstring === '') {
            return false;
        }

        $amount = $this->roundCurrencyAmount((float) ($charge['amount'] ?? 0));

        $toHash = 'x_id'.($charge['id'] ?? '')
            .'x_amount'.$amount
            .'x_currency'.($charge['currency'] ?? '')
            .'x_gateway_reference'.($charge['reference']['gateway'] ?? '')
            .'x_payment_reference'.($charge['reference']['payment'] ?? '')
            .'x_status'.($charge['status'] ?? '')
            .'x_created'.($charge['transaction']['created'] ?? '');

        return hash_equals(
            hash_hmac('sha256', $toHash, $secret),
            $postedHashstring,
        );
    }

    /**
     * Round an amount to the ISO decimal precision of the charge currency
     * (three places for KWD/BHD/OMR/JOD, two for USD/SAR/EUR/...).
     */
    public function roundCurrencyAmount(float $amount, ?string $currency = null): string
    {
        $threeDecimals = ['KWD', 'BHD', 'OMR', 'JOD'];

        $precision = in_array(strtoupper((string) ($currency ?? $this->currency())), $threeDecimals, true) ? 3 : 2;

        return number_format(round($amount, $precision), $precision, '.', '');
    }

    /**
     * Track the structured log of a settlement attempt for the finance team.
     *
     * @param  array<string, mixed>  $context
     */
    public function log(string $message, array $context = []): void
    {
        Log::channel(config('logging.default'))->info('[tap] '.$message, $context);
    }

    /**
     * Split a display name into Tap's first/last name parts.
     *
     * @return array{0: string, 1: string}
     */
    private function nameParts(string $name): array
    {
        $parts = preg_split('/\s+/', trim($name)) ?: [];

        if ($parts === []) {
            return ['Student', ''];
        }

        return [
            (string) array_shift($parts),
            (string) (array_pop($parts) ?: ''),
        ];
    }

    /**
     * Guard shared by the checkout actions: only published invoices may be paid.
     */
    public static function assertPayable(Invoice $invoice): void
    {
        if (! InvoiceStatus::tryFrom($invoice->status)?->isLive()) {
            throw new \RuntimeException('Invoice '.$invoice->number.' is not payable in its current state.');
        }

        if (Payment::query()->where('invoice_id', $invoice->id)->where('status', 'pending')->exists()) {
            throw new \RuntimeException('A payment attempt is already running for this invoice.');
        }
    }
}
