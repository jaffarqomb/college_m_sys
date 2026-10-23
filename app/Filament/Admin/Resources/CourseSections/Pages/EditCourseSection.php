<?php

namespace App\Filament\Admin\Resources\CourseSections\Pages;

use App\Filament\Admin\Resources\CourseSections\CourseSectionResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditCourseSection extends EditRecord
{
    protected static string $resource = CourseSectionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
