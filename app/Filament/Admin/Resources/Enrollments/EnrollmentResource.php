<?php

namespace App\Filament\Admin\Resources\Enrollments;

use App\Filament\Admin\Resources\Enrollments\Pages\CreateEnrollment;
use App\Filament\Admin\Resources\Enrollments\Pages\EditEnrollment;
use App\Filament\Admin\Resources\Enrollments\Pages\ListEnrollments;
use App\Filament\Admin\Resources\Enrollments\Schemas\EnrollmentForm;
use App\Filament\Admin\Resources\Enrollments\Tables\EnrollmentsTable;
use App\Models\Enrollment;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class EnrollmentResource extends Resource
{
    protected static ?string $model = Enrollment::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedAcademicCap;

    protected static string|UnitEnum|null $navigationGroup = 'People';

    protected static ?int $navigationSort = 3;

    protected static ?string $recordTitleAttribute = 'id';

    protected static ?string $modelLabel = 'Enrollment';

    public static function form(Schema $schema): Schema
    {
        return EnrollmentForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return EnrollmentsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    /**
     * Department heads only see enrollments of students in their own department.
     */
    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();

        $user = auth()->user();

        if ($user?->isDepartmentHead()) {
            $departmentId = $user->managedDepartmentId();

            $query->whereHas('student.program', fn (Builder $q): Builder => $q->where('department_id', $departmentId));
        }

        return $query;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListEnrollments::route('/'),
            'create' => CreateEnrollment::route('/create'),
            'edit' => EditEnrollment::route('/{record}/edit'),
        ];
    }
}
