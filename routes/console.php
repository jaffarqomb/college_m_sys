<?php

use App\Models\TeamInvitation;
use App\Services\Finance\InvoiceService;
use Illuminate\Support\Facades\Schedule;

Schedule::call(function () {
    TeamInvitation::query()
        ->whereNotNull('expires_at')
        ->where('expires_at', '<', now())
        ->delete();
})->daily()->description('Delete expired team invitations');

Schedule::call(fn (): int => app(InvoiceService::class)->markOverdue())
    ->daily()
    ->description('Mark unpaid invoices past their due date as overdue');

Schedule::command('college:backup')
    ->dailyAt('02:00')
    ->description('Snapshot the SQLite database onto the backup disk and prune old backups');
