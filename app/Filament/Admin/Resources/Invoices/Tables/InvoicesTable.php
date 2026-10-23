<?php

namespace App\Filament\Admin\Resources\Invoices\Tables;

use App\Actions\Finance\PublishInvoice;
use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class InvoicesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('number')
                    ->label('Number')
                    ->searchable()
                    ->sortable()
                    ->weight('semibold'),

                TextColumn::make('student.user.name')
                    ->label('Student')
                    ->description(fn (Invoice $record): string => $record->student->student_number)
                    ->searchable()
                    ->sortable(),

                TextColumn::make('term.name')
                    ->label('Term')
                    ->sortable(),

                TextColumn::make('total')
                    ->label('Total')
                    ->numeric()
                    ->sortable(),

                TextColumn::make('balance')
                    ->label('Balance')
                    ->state(fn (Invoice $record): float => $record->balance())
                    ->numeric()
                    ->sortable(query: function ($query, string $direction): mixed {
                        // Balance sorting approximated by total minus succeeded payments.
                        return $query->orderByRaw('total - (select coalesce(sum(p.amount), 0) from payments p where p.invoice_id = invoices.id and p.status = ?) '.$direction, ['succeeded']);
                    }),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (Invoice $record): string => InvoiceStatus::from($record->status)->label())
                    ->color(fn (Invoice $record): string => InvoiceStatus::from($record->status)->color()),

                TextColumn::make('due_date')
                    ->date()
                    ->label('Due')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(InvoiceStatus::class)
                    ->default(null),

                SelectFilter::make('term')
                    ->relationship('term', 'name')
                    ->preload(),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),

                Action::make('publish')
                    ->label('Publish')
                    ->icon('heroicon-o-paper-airplane')
                    ->visible(fn (Invoice $record): bool => $record->status === InvoiceStatus::Draft->value)
                    ->requiresConfirmation()
                    ->action(function (Invoice $record): void {
                        app(PublishInvoice::class)->handle($record);
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([

                ]),
            ]);
    }
}
