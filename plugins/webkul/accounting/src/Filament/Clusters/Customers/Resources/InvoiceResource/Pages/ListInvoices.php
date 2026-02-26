<?php

namespace Webkul\Accounting\Filament\Clusters\Customers\Resources\InvoiceResource\Pages;

use Filament\Actions\CreateAction;
use Webkul\Account\Filament\Resources\InvoiceResource\Pages\ListInvoices as BaseListInvoices;
use Webkul\Accounting\Filament\Clusters\Customers\Resources\InvoiceResource;

class ListInvoices extends BaseListInvoices
{
    protected static string $resource = InvoiceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('New Invoice')
                ->icon('heroicon-o-plus'),
        ];
    }
}
