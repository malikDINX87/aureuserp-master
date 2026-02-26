<?php

namespace Webkul\DinxCommerce\Filament\Admin\Actions;

use Filament\Actions\Action;
use Webkul\Account\Models\Move;
use Webkul\DinxCommerce\Filament\Admin\Resources\DinxContractResource;
use Webkul\DinxCommerce\Models\DinxContractInvoiceLink;

class OpenContractAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'dinx.open_contract';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->label('Open Contract')
            ->icon('heroicon-o-document-text')
            ->color('gray')
            ->visible(function (Move $record): bool {
                return DinxContractInvoiceLink::query()
                    ->where('invoice_id', $record->id)
                    ->exists();
            })
            ->url(function (Move $record): string {
                $contractId = DinxContractInvoiceLink::query()
                    ->where('invoice_id', $record->id)
                    ->value('contract_id');

                if (! $contractId) {
                    return '#';
                }

                return DinxContractResource::getUrl('view', ['record' => $contractId]);
            })
            ->openUrlInNewTab();
    }
}
