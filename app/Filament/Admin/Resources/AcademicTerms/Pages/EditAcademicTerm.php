<?php

namespace App\Filament\Admin\Resources\AcademicTerms\Pages;

use App\Filament\Admin\Resources\AcademicTerms\AcademicTermResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditAcademicTerm extends EditRecord
{
    protected static string $resource = AcademicTermResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
