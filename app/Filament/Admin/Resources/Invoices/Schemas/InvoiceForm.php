<?php

namespace App\Filament\Admin\Resources\Invoices\Schemas;

use App\Models\Student;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class InvoiceForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('student_id')
                    ->label('Student')
                    ->relationship('student', 'student_number')
                    ->getOptionLabelFromRecordUsing(fn (Student $record): string => $record->displayName().' ('.$record->student_number.')')
                    ->searchable(['student_number', 'user.name'])
                    ->preload()
                    ->required(),

                Select::make('term_id')
                    ->label('Term')
                    ->relationship('term', 'name')
                    ->preload()
                    ->required(),

                TextInput::make('number')
                    ->label('Invoice number')
                    ->helperText('Left empty, a sequential number is generated on save.')
                    ->maxLength(40)
                    ->unique(ignoreRecord: true),

                DatePicker::make('issue_date')
                    ->default(now()->toDateString())
                    ->required(),

                DatePicker::make('due_date')
                    ->default(now()->addDays(30)->toDateString())
                    ->afterOrEqual('issue_date')
                    ->required(),

                TextInput::make('total')
                    ->label('Total')
                    ->helperText('Recomputed automatically as line items change on the edit screen.')
                    ->numeric()
                    ->disabled()
                    ->dehydrated(false),

                Hidden::make('status')
                    ->default('draft'),
            ]);
    }
}
