<?php

namespace App\Filament\Admin\Resources\Assessments;

use App\Filament\Admin\Resources\Assessments\Pages\CreateAssessment;
use App\Filament\Admin\Resources\Assessments\Pages\EditAssessment;
use App\Filament\Admin\Resources\Assessments\Pages\ListAssessments;
use App\Filament\Admin\Resources\Assessments\Schemas\AssessmentForm;
use App\Filament\Admin\Resources\Assessments\Tables\AssessmentsTable;
use App\Models\Assessment;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class AssessmentsResource extends Resource
{
    protected static ?string $model = Assessment::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static string|UnitEnum|null $navigationGroup = 'Academic Structure';

    protected static ?int $navigationSort = 8;

    protected static ?string $recordTitleAttribute = 'title';

    public static function form(Schema $schema): Schema
    {
        return AssessmentForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AssessmentsTable::configure($table);
    }

    /**
     * @param  Assessment|null  $record
     */
    public static function getRecordTitle(?Model $record): string|Htmlable|null
    {
        return $record === null ? static::getModelLabel() : $record->section->fullCode().' — '.$record->title;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAssessments::route('/'),
            'create' => CreateAssessment::route('/create'),
            'edit' => EditAssessment::route('/{record}/edit'),
        ];
    }

    /**
     * Department heads only see assessments of courses in their own department.
     */
    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();

        $user = auth()->user();

        if ($user?->isDepartmentHead()) {
            $departmentId = $user->managedDepartmentId();

            $query->whereHas('section.course', fn (Builder $q): Builder => $q->where('department_id', $departmentId));
        }

        return $query;
    }
}
