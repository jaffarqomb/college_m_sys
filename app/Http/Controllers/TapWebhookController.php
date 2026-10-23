<?php

namespace App\Http\Controllers;

use App\Actions\Finance\SettleTapPayment;
use App\Services\Finance\TapGateway;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Handles the server-to-server Tap webhook ("post.url"). Tap signs the charge
 * payload with a hashstring header; only verified posts settle money state.
 * Replays are idempotent — the same charge may be posted multiple times.
 */
class TapWebhookController extends Controller
{
    public function __construct(
        private readonly TapGateway $gateway,
        private readonly SettleTapPayment $settle,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        // Webhooks arrive as JSON; ternary keeps form-encoded probe posts usable too.
        $charge = $request->all();

        if ($charge === []) {
            return response()->json(['message' => 'Empty webhook payload.'], 400);
        }

        if (! $this->gateway->verifyHashstring($charge, $request->header('hashstring'))) {
            $this->gateway->log('Webhook rejected: hashstring mismatch', [
                'charge_id' => (string) ($charge['id'] ?? 'unknown'),
            ]);

            return response()->json(['message' => 'Invalid webhook signature.'], 403);
        }

        $chargeId = (string) $charge['id'];

        try {
            $payment = $this->settle->handle($chargeId, $charge);
        } catch (\Throwable $exception) {
            $this->gateway->log('Webhook settlement error', [
                'charge_id' => $chargeId,
                'error' => $exception->getMessage(),
            ]);

            // 200 anyway so Tap does not retry a poisoned payload forever.
            return response()->json(['message' => 'Webhook accepted.']);
        }

        return response()->json([
            'message' => 'Webhook accepted.',
            'settled' => $payment?->status,
        ]);
    }
}
