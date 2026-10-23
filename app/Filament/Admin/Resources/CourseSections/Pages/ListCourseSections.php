<?php

namespace App\Filament\Admin\Resources\CourseSections\Pages;

use App\Filament\Admin\Resources\CourseSections\CourseSectionResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListCourseSections extends ListRecords
{
    protected static string $resource = CourseSectionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
