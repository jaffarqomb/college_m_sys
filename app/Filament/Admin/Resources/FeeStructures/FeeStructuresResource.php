<?php

namespace App\Filament\Admin\Resources\FeeStructures;

use App\Filament\Admin\Resources\FeeStructures\Pages\CreateFeeStructure;
use App\Filament\Admin\Resources\FeeStructures\Pages\EditFeeStructure;
use App\Filament\Admin\Resources\FeeStructures\Pages\ListFeeStructures;
use App\Filament\Admin\Resources\FeeStructures\Schemas\FeeStructureForm;
use App\Filament\Admin\Resources\FeeStructures\Tables\FeeStructuresTable;
use App\Models\FeeStructure;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class FeeStructuresResource extends Resource
{
    protected static ?string $model = FeeStructure::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentCurrencyDollar;

    protected static string|UnitEnum|null $navigationGroup = 'Finance';

    protected static ?int $navigationSort = 10;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return FeeStructureForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return FeeStructuresTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListFeeStructures::route('/'),
            'create' => CreateFeeStructure::route('/create'),
            'edit' => EditFeeStructure::route('/{record}/edit'),
        ];
    }
}
