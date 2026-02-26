<?php

namespace Webkul\DinxCommerce\Filament\Admin\Resources\DinxContractResource\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Webkul\DinxCommerce\Filament\Admin\Resources\DinxContractResource;

class ListDinxContracts extends ListRecords
{
    protected static string $resource = DinxContractResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
