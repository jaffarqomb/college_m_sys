<?php

namespace App\Filament\Admin\Resources\Invoices\RelationManagers;

use App\Models\Invoice;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ItemsRelationManager extends RelationManager
{
    protected static string $relationship = 'items';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('description')
                    ->label('Description')
                    ->required()
                    ->maxLength(255)
                    ->columnSpanFull(),

                TextInput::make('amount')
                    ->label('Amount')
                    ->numeric()
                    ->minValue(0)
                    ->required(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('description')
            ->columns([
                TextColumn::make('description')
                    ->label('Description')
                    ->searchable(),

                TextColumn::make('amount')
                    ->label('Amount')
                    ->numeric()
                    ->sortable(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->headerActions([
                CreateAction::make(),
            ]);
    }

    /**
     * Realigns the invoice total after any line item mutation.
     */
    protected function afterSave(): void
    {
        $this->realignInvoiceTotal();
    }

    protected function afterCreate(): void
    {
        $this->realignInvoiceTotal();
    }

    protected function afterDelete(): void
    {
        $this->realignInvoiceTotal();
    }

    private function realignInvoiceTotal(): void
    {
        /** @var Invoice $invoice */
        $invoice = $this->getOwnerRecord();

        $invoice->update(['total' => (float) $invoice->items()->sum('amount')]);
    }
}
