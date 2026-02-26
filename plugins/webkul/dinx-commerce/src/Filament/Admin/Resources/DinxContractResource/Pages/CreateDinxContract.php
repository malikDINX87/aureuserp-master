<?php

namespace Webkul\DinxCommerce\Filament\Admin\Resources\DinxContractResource\Pages;

use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;
use Webkul\DinxCommerce\Filament\Admin\Resources\DinxContractResource;

class CreateDinxContract extends CreateRecord
{
    protected static string $resource = DinxContractResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['created_by'] = Auth::id();

        return $data;
    }
}
