<?php

namespace Webkul\DinxCommerce\Filament\Admin\Clusters\Settings\Pages;

use BackedEnum;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Pages\SettingsPage;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use UnitEnum;
use Webkul\DinxCommerce\Settings\DinxCommerceSettings;
use Webkul\Support\Filament\Clusters\Settings;

class ManageDinxCommerce extends SettingsPage
{
    use HasPageShield;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-banknotes';

    protected static string|UnitEnum|null $navigationGroup = 'Integrations';

    protected static ?string $slug = 'integrations/dinx-commerce';

    protected static ?int $navigationSort = 11;

    protected static string $settings = DinxCommerceSettings::class;

    protected static ?string $cluster = Settings::class;

    protected static function getPagePermission(): ?string
    {
        return 'page_dinx_commerce_manage_settings';
    }

    public function getTitle(): string
    {
        return 'DINX Commerce';
    }

    public static function getNavigationLabel(): string
    {
        return 'DINX Commerce';
    }

    public function getBreadcrumbs(): array
    {
        return [
            'DINX Commerce',
        ];
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('PayPal')
                    ->schema([
                        Select::make('paypal_mode')
                            ->options([
                                'sandbox' => 'Sandbox',
                                'live' => 'Live',
                            ])
                            ->default('sandbox')
                            ->required(),
                        TextInput::make('paypal_client_id')
                            ->label('Client ID')
                            ->columnSpanFull(),
                        TextInput::make('paypal_client_secret')
                            ->label('Client Secret')
                            ->password()
                            ->revealable()
                            ->columnSpanFull(),
                        TextInput::make('paypal_webhook_id')
                            ->label('Webhook ID'),
                        TextInput::make('paypal_brand_name')
                            ->label('Brand Name')
                            ->default('DINX'),
                    ])
                    ->columns(2)
                    ->columnSpanFull(),

                Section::make('DocuSign')
                    ->schema([
                        TextInput::make('docusign_account_id')
                            ->label('Account ID'),
                        TextInput::make('docusign_integration_key')
                            ->label('Integration Key'),
                        TextInput::make('docusign_user_id')
                            ->label('User ID'),
                        TextInput::make('docusign_base_uri')
                            ->label('Base URI')
                            ->placeholder('https://demo.docusign.net'),
                        TextInput::make('docusign_private_key_path')
                            ->label('Private Key Path')
                            ->columnSpanFull(),
                        TextInput::make('docusign_webhook_secret')
                            ->label('Webhook Secret')
                            ->password()
                            ->revealable()
                            ->columnSpanFull(),
                    ])
                    ->columns(2)
                    ->columnSpanFull(),
            ]);
    }
}
