<?php

namespace App\Filament\Admin\Resources\FeeStructures\Pages;

use App\Filament\Admin\Resources\FeeStructures\FeeStructuresResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditFeeStructure extends EditRecord
{
    protected static string $resource = FeeStructuresResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
