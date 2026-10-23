<?php

namespace App\Filament\Admin\Widgets;

use App\Enums\InvoiceStatus;
use App\Enums\PaymentStatus;
use App\Enums\UserRole;
use App\Models\Payment;
use App\Models\User;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;

class RevenueOverview extends BaseWidget
{
    protected ?string $heading = 'Revenue (current term)';

    protected ?string $pollingInterval = '60s';

    /**
     * @return array<Stat>
     */
    protected function getStats(): array
    {
        $term = DB::table('academic_terms')->where('is_current', true)->first();

        if ($term === null) {
            return [
                Stat::make('Invoiced', '—')
                    ->description('No current term is set')
                    ->color('gray'),
            ];
        }

        $invoiced = (float) DB::table('invoices')
            ->where('term_id', $term->id)
            ->sum('total');

        $invCount = (int) DB::table('invoices')->where('term_id', $term->id)->count();

        $paid = (float) Payment::query()
            ->where('payments.status', PaymentStatus::Succeeded->value)
            ->whereHas('invoice', fn ($query) => $query->where('invoices.term_id', $term->id))
            ->sum('payments.amount');
        $paid = min($invoiced, $paid);
        $outstanding = max(0.0, $invoiced - $paid);
        $percent = $invoiced > 0.0 ? round(($paid / $invoiced) * 100, 1) : 0.0;

        $overdue = (int) DB::table('invoices')
            ->where('term_id', $term->id)
            ->where('status', InvoiceStatus::Overdue->value)
            ->count();

        return [
            Stat::make('Invoiced', $this->kwd($invoiced).' KWD')
                ->description("{$invCount} invoice(s) issued")
                ->descriptionIcon('heroicon-m-document-text')
                ->color('primary'),

            Stat::make('Collected', $this->kwd($paid).' KWD')
                ->description("{$percent}% of invoiced collected")
                ->descriptionIcon('heroicon-m-banknotes')
                ->color($percent >= 50.0 ? 'success' : 'warning'),

            Stat::make('Outstanding', $this->kwd($outstanding).' KWD')
                ->description($outstanding > 0.0 ? "{$overdue} overdue invoice(s)" : 'Nothing overdue')
                ->descriptionIcon('heroicon-m-exclamation-triangle')
                ->color($outstanding > 0.0 ? 'danger' : 'success'),
        ];
    }

    private function kwd(float $amount): string
    {
        return number_format($amount, 3);
    }

    public static function canView(): bool
    {
        $role = self::panelRole();

        return in_array($role, [UserRole::FinanceOfficer, UserRole::Registrar, UserRole::SystemAdmin], true);
    }

    private static function panelRole(): ?UserRole
    {
        $user = auth()->user();

        return $user instanceof User ? $user->role : null;
    }
}
