<?php

namespace Webkul\DinxErpSync\Filament\Admin\Resources;

use BackedEnum;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;
use Webkul\DinxErpSync\Filament\Admin\Resources\DinxSyncLogResource\Pages\ListDinxSyncLogs;
use Webkul\DinxErpSync\Models\DinxSyncLog;

class DinxSyncLogResource extends Resource
{
    protected static ?string $model = DinxSyncLog::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-inbox-arrow-down';

    protected static ?string $navigationLabel = 'Sync Logs';

    protected static string|UnitEnum|null $navigationGroup = 'DINX ERP Sync';

    protected static ?int $navigationSort = 20;

    public static function getModelLabel(): string
    {
        return 'Sync Log';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Sync Logs';
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('delivery_id')
                    ->label('Delivery ID')
                    ->searchable()
                    ->copyable()
                    ->limit(24),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'processed' => 'success',
                        'queued', 'processing', 'received' => 'info',
                        'failed' => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('external_lead_id')
                    ->label('Lead ID')
                    ->searchable()
                    ->copyable(),
                TextColumn::make('partner.name')
                    ->label('Partner')
                    ->searchable(),
                TextColumn::make('error_message')
                    ->label('Error')
                    ->limit(60)
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->wrap(),
                TextColumn::make('processed_at')
                    ->label('Processed At')
                    ->dateTime(),
                TextColumn::make('created_at')
                    ->label('Received At')
                    ->dateTime(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'received' => 'Received',
                        'queued' => 'Queued',
                        'processing' => 'Processing',
                        'processed' => 'Processed',
                        'failed' => 'Failed',
                    ]),
            ])
            ->defaultSort('created_at', 'desc')
            ->recordActions([])
            ->bulkActions([]);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDinxSyncLogs::route('/'),
        ];
    }
}
