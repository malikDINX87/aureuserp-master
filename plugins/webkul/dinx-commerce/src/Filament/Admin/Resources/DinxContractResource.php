<?php

namespace Webkul\DinxCommerce\Filament\Admin\Resources;

use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;
use Webkul\DinxCommerce\Filament\Admin\Resources\DinxContractResource\Pages\CreateDinxContract;
use Webkul\DinxCommerce\Filament\Admin\Resources\DinxContractResource\Pages\EditDinxContract;
use Webkul\DinxCommerce\Filament\Admin\Resources\DinxContractResource\Pages\ListDinxContracts;
use Webkul\DinxCommerce\Filament\Admin\Resources\DinxContractResource\Pages\ViewDinxContract;
use Webkul\DinxCommerce\Models\DinxContract;

class DinxContractResource extends Resource
{
    protected static ?string $model = DinxContract::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-document-check';

    protected static string|UnitEnum|null $navigationGroup = 'DINX Commerce';

    protected static ?int $navigationSort = 10;

    public static function getModelLabel(): string
    {
        return 'Contract';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Contracts';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('partner_id')
                    ->relationship(
                        'partner',
                        'name',
                        modifyQueryUsing: fn (Builder $query) => $query->withTrashed()
                    )
                    ->searchable()
                    ->preload()
                    ->required(),
                TextInput::make('title')
                    ->required()
                    ->maxLength(255),
                Select::make('status')
                    ->options([
                        'draft' => 'Draft',
                        'sent' => 'Sent',
                        'viewed' => 'Viewed',
                        'completed' => 'Completed',
                        'declined' => 'Declined',
                        'voided' => 'Voided',
                    ])
                    ->default('draft')
                    ->required(),
                Select::make('currency_id')
                    ->relationship('currency', 'name')
                    ->searchable()
                    ->preload(),
                Select::make('invoices')
                    ->relationship('invoices', 'name')
                    ->multiple()
                    ->searchable()
                    ->preload()
                    ->columnSpanFull(),
                TextInput::make('amount_total')
                    ->numeric()
                    ->step('0.01')
                    ->default(0),
                DatePicker::make('effective_date')
                    ->native(false),
                DatePicker::make('expiration_date')
                    ->native(false),
                TextInput::make('docusign_envelope_id')
                    ->maxLength(255),
                TextInput::make('signed_document_path')
                    ->maxLength(2048),
                RichEditor::make('terms_html')
                    ->label('Contract Terms')
                    ->columnSpanFull(),
            ])
            ->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->sortable(),
                TextColumn::make('title')
                    ->searchable()
                    ->limit(40),
                TextColumn::make('partner.name')
                    ->label('Client')
                    ->searchable(),
                TextColumn::make('status')
                    ->badge(),
                TextColumn::make('amount_total')
                    ->money(fn (Model $record) => $record->currency?->name ?? 'USD')
                    ->label('Amount'),
                TextColumn::make('signed_at')
                    ->dateTime(),
                TextColumn::make('updated_at')
                    ->since(),
            ])
            ->defaultSort('id', 'desc')
            ->recordActions([
                Action::make('open_docusign_envelope')
                    ->label('DocuSign')
                    ->icon('heroicon-o-paper-airplane')
                    ->visible(fn (DinxContract $record): bool => ! empty($record->docusign_envelope_id))
                    ->url(fn (DinxContract $record): string => 'https://demo.docusign.net/Signing/StartInSession.aspx?envelope_id='.$record->docusign_envelope_id)
                    ->openUrlInNewTab(),
            ]);
    }

    public static function canDelete(Model $record): bool
    {
        return $record->status === 'draft';
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDinxContracts::route('/'),
            'create' => CreateDinxContract::route('/create'),
            'view' => ViewDinxContract::route('/{record}'),
            'edit' => EditDinxContract::route('/{record}/edit'),
        ];
    }
}
