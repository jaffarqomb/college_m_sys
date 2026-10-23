<?php

namespace App\Filament\Admin\Resources\Invoices\Pages;

use App\Enums\InvoiceStatus;
use App\Filament\Admin\Resources\Invoices\InvoicesResource;
use App\Services\Finance\InvoiceService;
use Filament\Resources\Pages\CreateRecord;

class CreateInvoice extends CreateRecord
{
    protected static string $resource = InvoicesResource::class;

    /**
     * Drafts start unpublished, zero total (line items are added on the edit
     * screen), and a sequential invoice number when left blank.
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['status'] ??= InvoiceStatus::Draft->value;
        $data['total'] ??= 0;

        if (blank($data['number'] ?? null)) {
            $data['number'] = app(InvoiceService::class)->nextNumber();
        }

        return $data;
    }
}
