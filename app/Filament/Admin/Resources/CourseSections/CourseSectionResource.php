<?php

namespace App\Filament\Admin\Resources\CourseSections;

use App\Filament\Admin\Resources\CourseSections\Pages\CreateCourseSection;
use App\Filament\Admin\Resources\CourseSections\Pages\EditCourseSection;
use App\Filament\Admin\Resources\CourseSections\Pages\ListCourseSections;
use App\Filament\Admin\Resources\CourseSections\RelationManagers\SectionSchedulesRelationManager;
use App\Filament\Admin\Resources\CourseSections\Schemas\CourseSectionForm;
use App\Filament\Admin\Resources\CourseSections\Tables\CourseSectionsTable;
use App\Models\CourseSection;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class CourseSectionResource extends Resource
{
    protected static ?string $model = CourseSection::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTableCells;

    protected static string|UnitEnum|null $navigationGroup = 'Academic Structure';

    protected static ?int $navigationSort = 5;

    protected static ?string $recordTitleAttribute = 'section_number';

    public static function form(Schema $schema): Schema
    {
        return CourseSectionForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CourseSectionsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            SectionSchedulesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCourseSections::route('/'),
            'create' => CreateCourseSection::route('/create'),
            'edit' => EditCourseSection::route('/{record}/edit'),
        ];
    }
}
