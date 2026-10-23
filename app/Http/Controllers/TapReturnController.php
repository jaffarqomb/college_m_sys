<?php

namespace App\Http\Controllers;

use App\Actions\Finance\SettleTapPayment;
use App\Services\Finance\TapGateway;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Redirect target ("redirect.url") after the hosted checkout page. Charge id
 * arrives as tap_id (or tap_id1 fallback). The webhook is authoritative; this
 * route verifies the charge directly so the student sees an immediate result.
 */
class TapReturnController extends Controller
{
    public function __construct(
        private readonly TapGateway $gateway,
        private readonly SettleTapPayment $settle,
    ) {}

    public function __invoke(Request $request): RedirectResponse
    {
        $chargeId = (string) ($request->query('tap_id') ?? $request->query('tap_id1') ?? '');

        if ($chargeId === '' || ! $this->gateway->isConfigured()) {
            return redirect()
                ->route('dashboard', ['current_team' => $request->user()->currentTeam?->slug])
                ->with('toast', 'We could not verify that payment. Contact the finance office.');
        }

        $this->settle->handle($chargeId);

        return redirect()
            ->route('dashboard', ['current_team' => $request->user()->currentTeam?->slug])
            ->with('toast', 'Payment processed. Check your invoices for the final status.');
    }
}
