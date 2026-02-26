<?php

namespace Webkul\DinxCommerce\Filament\Admin\Resources;

use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;
use Webkul\DinxCommerce\Filament\Admin\Resources\DinxPayPalOrderResource\Pages\ListDinxPayPalOrders;
use Webkul\DinxCommerce\Filament\Admin\Resources\DinxPayPalOrderResource\Pages\ViewDinxPayPalOrder;
use Webkul\DinxCommerce\Models\DinxPayPalOrder;

class DinxPayPalOrderResource extends Resource
{
    protected static ?string $model = DinxPayPalOrder::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-credit-card';

    protected static string|UnitEnum|null $navigationGroup = 'DINX Commerce';

    protected static ?int $navigationSort = 11;

    public static function form(Schema $schema): Schema
    {
        return $schema;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')->sortable(),
                TextColumn::make('invoice.name')
                    ->label('Invoice')
                    ->searchable(),
                TextColumn::make('paypal_order_id')
                    ->copyable()
                    ->searchable()
                    ->limit(24),
                TextColumn::make('status')->badge(),
                TextColumn::make('amount')
                    ->money(fn (Model $record) => $record->currency ?: 'USD'),
                TextColumn::make('paypal_capture_id')
                    ->copyable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->limit(24),
                TextColumn::make('processed_at')->dateTime(),
                TextColumn::make('updated_at')->since(),
            ])
            ->defaultSort('id', 'desc');
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
            'index' => ListDinxPayPalOrders::route('/'),
            'view' => ViewDinxPayPalOrder::route('/{record}'),
        ];
    }
}
