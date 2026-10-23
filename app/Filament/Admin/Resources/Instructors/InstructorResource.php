<?php

namespace App\Filament\Admin\Resources\Instructors;

use App\Filament\Admin\Resources\Instructors\Pages\CreateInstructor;
use App\Filament\Admin\Resources\Instructors\Pages\EditInstructor;
use App\Filament\Admin\Resources\Instructors\Pages\ListInstructors;
use App\Filament\Admin\Resources\Instructors\Schemas\InstructorForm;
use App\Filament\Admin\Resources\Instructors\Tables\InstructorsTable;
use App\Models\Instructor;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class InstructorResource extends Resource
{
    protected static ?string $model = Instructor::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    protected static string|UnitEnum|null $navigationGroup = 'People';

    protected static ?int $navigationSort = 2;

    protected static ?string $recordTitleAttribute = 'employee_number';

    public static function form(Schema $schema): Schema
    {
        return InstructorForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return InstructorsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    /**
     * Department heads only see staff in their own department.
     */
    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();

        $user = auth()->user();

        if ($user?->isDepartmentHead()) {
            $query->where('department_id', $user->managedDepartmentId());
        }

        return $query;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListInstructors::route('/'),
            'create' => CreateInstructor::route('/create'),
            'edit' => EditInstructor::route('/{record}/edit'),
        ];
    }
}
