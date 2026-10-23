<?php

namespace App\Filament\Admin\Resources\AcademicTerms\Pages;

use App\Filament\Admin\Resources\AcademicTerms\AcademicTermResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListAcademicTerms extends ListRecords
{
    protected static string $resource = AcademicTermResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
