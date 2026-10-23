<?php

namespace App\Filament\Admin\Resources\FeeStructures\Pages;

use App\Filament\Admin\Resources\FeeStructures\FeeStructuresResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListFeeStructures extends ListRecords
{
    protected static string $resource = FeeStructuresResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
