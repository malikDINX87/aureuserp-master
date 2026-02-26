<?php

namespace Webkul\DinxErpSync\Filament\Admin\Resources\DinxSyncLogResource\Pages;

use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Webkul\DinxErpSync\Filament\Admin\Resources\DinxSyncLogResource;

class ListDinxSyncLogs extends ListRecords
{
    protected static string $resource = DinxSyncLogResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('refresh')
                ->label('Refresh')
                ->icon('heroicon-o-arrow-path')
                ->action(fn () => null),
        ];
    }
}
