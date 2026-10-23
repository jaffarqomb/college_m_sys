<?php

namespace App\Filament\Admin\Resources\Assessments\Pages;

use App\Filament\Admin\Resources\Assessments\AssessmentsResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditAssessment extends EditRecord
{
    protected static string $resource = AssessmentsResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
