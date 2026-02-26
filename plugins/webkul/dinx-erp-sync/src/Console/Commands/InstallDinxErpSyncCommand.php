<?php

namespace Webkul\DinxErpSync\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Throwable;
use Webkul\PluginManager\Models\Plugin;

class InstallDinxErpSyncCommand extends Command
{
    protected $signature = 'dinx-erp-sync:install {--force : Run migrations with --force}';

    protected $description = 'Install or repair DINX ERP Sync plugin migrations/settings and register plugin metadata';

    public function handle(): int
    {
        $this->info('Installing DINX ERP Sync plugin...');

        $force = (bool) $this->option('force') || app()->environment('production');

        $migrationPaths = [
            'plugins/webkul/dinx-erp-sync/database/migrations/2026_02_23_000001_create_dinx_sync_mappings_table.php',
            'plugins/webkul/dinx-erp-sync/database/migrations/2026_02_23_000002_create_dinx_sync_logs_table.php',
            'plugins/webkul/dinx-erp-sync/database/migrations/2026_02_24_000003_create_dinx_sso_user_mappings_table.php',
        ];

        $settingPaths = [
            'plugins/webkul/dinx-erp-sync/database/settings/2026_02_23_000003_create_dinx_erp_sync_settings.php',
            'plugins/webkul/dinx-erp-sync/database/settings/2026_02_24_000004_add_dinx_sso_settings.php',
        ];

        $this->runMigrations($migrationPaths, $force);
        $this->runMigrations($settingPaths, $force);

        Plugin::query()->updateOrCreate(
            ['name' => 'dinx-erp-sync'],
            [
                'author' => 'DINX Solutions',
                'summary' => 'DINX CRM to Aureus ERP sync and SSO bridge',
                'description' => 'Handles DINX conversion webhooks and DINX-to-ERP SSO sign-in flow.',
                'latest_version' => '1.0.0',
                'license' => 'MIT',
                'is_active' => true,
                'is_installed' => true,
            ]
        );

        $this->regeneratePermissions();

        $this->info('DINX ERP Sync plugin install completed successfully.');

        return self::SUCCESS;
    }

    protected function runMigrations(array $paths, bool $force): void
    {
        $arguments = [
            '--path' => $paths,
        ];

        if ($force) {
            $arguments['--force'] = true;
        }

        $this->call('migrate', $arguments);
    }

    protected function regeneratePermissions(): void
    {
        try {
            if (! $this->getApplication() || ! $this->getApplication()->has('shield:generate')) {
                return;
            }

            Artisan::call('shield:generate', [
                '--all' => true,
                '--option' => 'permissions',
                '--panel' => 'admin',
            ], $this->getOutput());
        } catch (Throwable $exception) {
            $this->warn('Permission regeneration skipped: '.$exception->getMessage());
        }
    }
}
