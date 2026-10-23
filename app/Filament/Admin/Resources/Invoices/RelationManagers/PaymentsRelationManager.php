<?php

namespace App\Filament\Admin\Resources\Invoices\RelationManagers;

use App\Actions\Finance\RecordManualPayment;
use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Invoice;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Support\Exceptions\Halt;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Validation\ValidationException;

class PaymentsRelationManager extends RelationManager
{
    protected static string $relationship = 'payments';

    public function form(Schema $schema): Schema
    {
        // Payments are recorded through the header action below; the manager
        // itself is read-only outside of it.
        return $schema->components([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->columns([
                TextColumn::make('amount')
                    ->label('Amount')
                    ->numeric()
                    ->sortable()
                    ->weight('semibold'),

                TextColumn::make('method')
                    ->label('Method')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => PaymentMethod::tryFrom($state)?->label() ?? $state)
                    ->color(fn (string $state): string => $state === PaymentMethod::Tap->value ? 'info' : 'gray'),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => PaymentStatus::tryFrom($state)?->label() ?? $state)
                    ->color(fn (string $state): string => match ($state) {
                        PaymentStatus::Succeeded->value => 'success',
                        PaymentStatus::Pending->value => 'warning',
                        default => 'danger',
                    }),

                TextColumn::make('paid_at')
                    ->label('Paid at')
                    ->dateTime()
                    ->placeholder('—')
                    ->sortable(),

                TextColumn::make('reference')
                    ->label('Reference')
                    ->placeholder('—')
                    ->copyable(),

                TextColumn::make('notes')
                    ->label('Notes')
                    ->limit(40)
                    ->placeholder('—')
                    ->toggleable(),
            ])
            ->headerActions([
                Action::make('recordPayment')
                    ->label('Record payment')
                    ->icon('heroicon-o-banknotes')
                    ->color('success')
                    ->visible(function (): bool {
                        /** @var Invoice $invoice */
                        $invoice = $this->getOwnerRecord();

                        return $invoice->balance() > 0 && (InvoiceStatus::tryFrom($invoice->status)?->isLive() ?? false);
                    })
                    ->schema(fn (Schema $schema): Schema => $schema->components([
                        TextInput::make('amount')
                            ->label('Amount')
                            ->numeric()
                            ->minValue(0.01)
                            ->default(function (): float {
                                /** @var Invoice $invoice */
                                $invoice = $this->getOwnerRecord();

                                return $invoice->balance();
                            })
                            ->required(),
                        TextInput::make('reference')
                            ->label('Reference')
                            ->helperText('Cash receipt number, bank transfer id...')
                            ->maxLength(255),
                        Textarea::make('notes')
                            ->label('Notes')
                            ->maxLength(255)
                            ->columnSpanFull(),
                    ]))
                    ->action(function (array $data): void {
                        /** @var Invoice $invoice */
                        $invoice = $this->getOwnerRecord();

                        try {
                            app(RecordManualPayment::class)->handle(
                                $invoice->refresh(),
                                (float) $data['amount'],
                                $data['reference'] ?? null,
                                $data['notes'] ?? null,
                            );
                        } catch (ValidationException $exception) {
                            Notification::make()
                                ->title(collect($exception->errors())->flatten()->first())
                                ->danger()
                                ->send();

                            throw new Halt;
                        }
                    }),
            ]);
    }
}
