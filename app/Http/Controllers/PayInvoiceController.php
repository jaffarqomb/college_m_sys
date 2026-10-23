<?php

namespace App\Http\Controllers;

use App\Actions\Finance\StartTapCheckout;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Kicks off the Tap hosted checkout for a student's own invoice and forwards
 * the browser to the hosted payment page.
 */
class PayInvoiceController extends Controller
{
    public function __invoke(Request $request, string $currentTeam, Invoice $invoice): RedirectResponse
    {
        $user = $request->user();

        abort_unless($this->authorisedFor($user, $invoice), 403);

        try {
            $checkout = app(StartTapCheckout::class)->handle($invoice, $user);
        } catch (\Throwable $exception) {
            report($exception);

            return redirect()
                ->route('dashboard', ['current_team' => $user->currentTeam?->slug])
                ->with('toast', 'We could not start the online payment. Contact the finance office.');
        }

        session(['tap.checkout_payment_id' => $checkout['payment']->id]);

        if ($checkout['redirect_url'] === null) {
            return redirect()
                ->route('dashboard', ['current_team' => $user->currentTeam?->slug])
                ->with('toast', 'The gateway did not return a payment page. Contact the finance office.');
        }

        return redirect()->away($checkout['redirect_url']);
    }

    /**
     * Students settle their own invoices; academic staff may pay on behalf of.
     */
    private function authorisedFor(User $user, Invoice $invoice): bool
    {
        return $user->studentProfile !== null
            ? $invoice->student_id === $user->studentProfile->id
            : $user->role->isRegistrar() || $user->role->isFinanceOfficer() || $user->role->isSystemAdmin();
    }
}
