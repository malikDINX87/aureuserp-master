<?php

namespace Webkul\DinxErpSync\Filament\Admin\Clusters\Settings\Pages;

use BackedEnum;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Pages\SettingsPage;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use UnitEnum;
use Webkul\DinxErpSync\Settings\DinxErpSyncSettings;
use Webkul\Support\Filament\Clusters\Settings;

class ManageDinxErpSync extends SettingsPage
{
    use HasPageShield;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-arrow-path-rounded-square';

    protected static string|UnitEnum|null $navigationGroup = 'Integrations';

    protected static ?string $slug = 'integrations/dinx-erp-sync';

    protected static ?int $navigationSort = 10;

    protected static string $settings = DinxErpSyncSettings::class;

    protected static ?string $cluster = Settings::class;

    protected static function getPagePermission(): ?string
    {
        return 'page_dinx_erp_sync_manage_settings';
    }

    public function getTitle(): string
    {
        return 'DINX ERP Sync';
    }

    public static function getNavigationLabel(): string
    {
        return 'DINX ERP Sync';
    }

    public function getBreadcrumbs(): array
    {
        return [
            'DINX ERP Sync',
        ];
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Webhook Security')
                    ->schema([
                        Toggle::make('enabled')
                            ->label('Enable inbound webhook processing')
                            ->helperText('If disabled, incoming webhook calls are accepted but not processed.'),
                        TextInput::make('webhook_secret')
                            ->label('Webhook secret')
                            ->password()
                            ->revealable()
                            ->autocomplete(false)
                            ->helperText('Leave empty to use DINX_ERP_WEBHOOK_SECRET from the environment.'),
                        TextInput::make('max_timestamp_skew_seconds')
                            ->label('Max timestamp skew (seconds)')
                            ->numeric()
                            ->minValue(30)
                            ->maxValue(3600)
                            ->default(300),
                        TextInput::make('processing_queue')
                            ->label('Queue name')
                            ->placeholder('default')
                            ->helperText('Optional. Leave empty to use the default queue connection.'),
                    ])
                    ->columns(2)
                    ->columnSpanFull(),

                Section::make('DINX SSO')
                    ->schema([
                        Toggle::make('sso_enabled')
                            ->label('Enable DINX SSO login bridge')
                            ->helperText('Controls whether /sso/dinx accepts SSO tickets.'),
                        TextInput::make('sso_shared_secret')
                            ->label('SSO shared secret')
                            ->password()
                            ->revealable()
                            ->autocomplete(false)
                            ->helperText('Leave empty to use DINX_ERP_SSO_SHARED_SECRET from the environment.'),
                        TextInput::make('sso_issuer')
                            ->label('Allowed issuer')
                            ->placeholder('dinxsolutions.com')
                            ->helperText('Fallback env: DINX_ERP_SSO_ISSUER'),
                        TextInput::make('sso_audience')
                            ->label('Allowed audience')
                            ->placeholder('dinx-erp')
                            ->helperText('Fallback env: DINX_ERP_SSO_AUDIENCE'),
                        TextInput::make('sso_max_clock_skew_seconds')
                            ->label('Max clock skew (seconds)')
                            ->numeric()
                            ->minValue(5)
                            ->maxValue(600)
                            ->default(60),
                        TextInput::make('sso_jti_ttl_seconds')
                            ->label('Replay cache TTL (seconds)')
                            ->numeric()
                            ->minValue(60)
                            ->maxValue(7200)
                            ->default(600),
                    ])
                    ->columns(2)
                    ->columnSpanFull(),
            ]);
    }
}
