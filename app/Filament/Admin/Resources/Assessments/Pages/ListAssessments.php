<?php

namespace App\Filament\Admin\Resources\Assessments\Pages;

use App\Filament\Admin\Resources\Assessments\AssessmentsResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListAssessments extends ListRecords
{
    protected static string $resource = AssessmentsResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
