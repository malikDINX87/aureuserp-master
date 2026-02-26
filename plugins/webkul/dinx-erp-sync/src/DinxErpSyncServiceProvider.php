<?php

namespace Webkul\DinxErpSync;

use Filament\Panel;
use Webkul\DinxErpSync\Console\Commands\InstallDinxErpSyncCommand;
use Webkul\DinxErpSync\Console\Commands\MigrateLocalSqliteCommand;
use Webkul\PluginManager\Console\Commands\UninstallCommand;
use Webkul\PluginManager\Package;
use Webkul\PluginManager\PackageServiceProvider;

class DinxErpSyncServiceProvider extends PackageServiceProvider
{
    public static string $name = 'dinx-erp-sync';

    public function configureCustomPackage(Package $package): void
    {
        $package->name(static::$name)
            ->hasRoutes(['api', 'web'])
            ->hasMigrations([
                '2026_02_23_000001_create_dinx_sync_mappings_table',
                '2026_02_23_000002_create_dinx_sync_logs_table',
                '2026_02_24_000003_create_dinx_sso_user_mappings_table',
            ])
            ->runsMigrations()
            ->hasSettings([
                '2026_02_23_000003_create_dinx_erp_sync_settings',
                '2026_02_24_000004_add_dinx_sso_settings',
            ])
            ->runsSettings()
            ->hasCommand(InstallDinxErpSyncCommand::class)
            ->hasCommand(MigrateLocalSqliteCommand::class)
            ->hasDependencies([
                'partners',
                'contacts',
            ])
            ->hasUninstallCommand(function (UninstallCommand $command) {})
            ->icon('contacts');
    }

    public function packageBooted(): void
    {
        //
    }

    public function packageRegistered(): void
    {
        Panel::configureUsing(function (Panel $panel): void {
            $panel->plugin(DinxErpSyncPlugin::make());
        });
    }
}
