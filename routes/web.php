<?php

use App\Http\Controllers\PayInvoiceController;
use App\Http\Controllers\TapReturnController;
use App\Http\Controllers\TapWebhookController;
use App\Http\Middleware\EnsureTeamMembership;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Support\Facades\Route;

// Tap POSTs its webhook server-to-server; CSRF does not apply.
Route::post('payments/tap/webhook', TapWebhookController::class)
    ->name('tap.webhook')
    ->withoutMiddleware([VerifyCsrfToken::class]);

Route::view('/', 'site.home')->name('home');

Route::view('about', 'site.about')->name('about');

Route::view('contact', 'site.contact')->name('contact');

Route::prefix('{current_team}')
    ->middleware(['auth', 'verified', EnsureTeamMembership::class])
    ->group(function () {
        Route::view('dashboard', 'dashboard')->name('dashboard');
        Route::view('transcript', 'pages.transcript')->name('transcript');

        // Tap returns the payer here once the hosted checkout completes.
        Route::get('payments/tap/return', TapReturnController::class)->name('tap.return');

        Route::get('invoices/{invoice}/pay', PayInvoiceController::class)->name('invoices.pay');
    });

require __DIR__.'/settings.php';
