<?php

namespace Webkul\DinxCommerce\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Throwable;
use Webkul\PluginManager\Models\Plugin;

class InstallDinxCommerceCommand extends Command
{
    protected $signature = 'dinx-commerce:install {--force : Run migrations with --force}';

    protected $description = 'Install or repair DINX Commerce plugin migrations/settings and register plugin metadata';

    public function handle(): int
    {
        $this->info('Installing DINX Commerce plugin...');

        $force = (bool) $this->option('force') || app()->environment('production');

        $migrationPaths = [
            'plugins/webkul/dinx-commerce/database/migrations/2026_02_24_100001_create_dinx_contracts_table.php',
            'plugins/webkul/dinx-commerce/database/migrations/2026_02_24_100002_create_dinx_contract_invoice_links_table.php',
            'plugins/webkul/dinx-commerce/database/migrations/2026_02_24_100003_create_dinx_contract_payment_links_table.php',
            'plugins/webkul/dinx-commerce/database/migrations/2026_02_24_100004_create_dinx_contract_events_table.php',
            'plugins/webkul/dinx-commerce/database/migrations/2026_02_24_100005_create_dinx_paypal_orders_table.php',
            'plugins/webkul/dinx-commerce/database/migrations/2026_02_26_000001_create_dinx_project_invoice_links_table.php',
            'plugins/webkul/dinx-commerce/database/migrations/2026_02_26_000002_create_dinx_contract_versions_table.php',
            'plugins/webkul/dinx-commerce/database/migrations/2026_02_26_000003_create_dinx_recurring_invoice_profiles_table.php',
            'plugins/webkul/dinx-commerce/database/migrations/2026_02_26_000004_create_dinx_bank_statement_imports_table.php',
            'plugins/webkul/dinx-commerce/database/migrations/2026_02_26_000005_create_dinx_bank_statement_lines_table.php',
            'plugins/webkul/dinx-commerce/database/migrations/2026_02_26_000006_create_dinx_bank_reconciliation_matches_table.php',
            'plugins/webkul/dinx-commerce/database/migrations/2026_02_26_000007_create_dinx_expense_category_rules_table.php',
            'plugins/webkul/dinx-commerce/database/migrations/2026_02_26_000008_create_dinx_tax_mapper_rules_table.php',
            'plugins/webkul/dinx-commerce/database/migrations/2026_02_26_000009_create_dinx_report_favorites_table.php',
        ];

        $settingsPaths = [
            'plugins/webkul/dinx-commerce/database/settings/2026_02_24_100006_create_dinx_commerce_settings.php',
            'plugins/webkul/dinx-commerce/database/settings/2026_02_26_000010_create_dinx_workspace_settings.php',
        ];

        $this->runMigrations($migrationPaths, $force);
        $this->runMigrations($settingsPaths, $force);

        Plugin::query()->updateOrCreate(
            ['name' => 'dinx-commerce'],
            [
                'author' => 'DINX Solutions',
                'summary' => 'DINX contracts, PayPal billing, and DocuSign integration',
                'description' => 'Adds contract management, PayPal payment links, and DocuSign webhook reconciliation.',
                'latest_version' => '1.0.0',
                'license' => 'MIT',
                'is_active' => true,
                'is_installed' => true,
            ]
        );

        $this->ensureWorkspacePermissions();
        $this->assignWorkspacePermissionsToDefaultRoles();

        $this->regeneratePermissions();

        $this->info('DINX Commerce plugin install completed successfully.');

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

    protected function ensureWorkspacePermissions(): void
    {
        $permissions = $this->workspacePermissionNames();

        foreach ($permissions as $permission) {
            Permission::query()->firstOrCreate([
                'name' => $permission,
                'guard_name' => 'web',
            ]);
        }
    }

    protected function assignWorkspacePermissionsToDefaultRoles(): void
    {
        $permissions = $this->workspacePermissionNames();

        $roles = Role::query()
            ->where('guard_name', 'web')
            ->whereIn('name', ['Admin', 'GlobalAdmin', 'Super Admin'])
            ->get();

        foreach ($roles as $role) {
            $base = $role->permissions
                ->pluck('name')
                ->reject(fn (string $name): bool => str_starts_with($name, 'page_dinx_workspace_'))
                ->values()
                ->all();

            $role->syncPermissions(array_values(array_unique(array_merge($base, $permissions))));
        }
    }

    /**
     * @return array<int, string>
     */
    protected function workspacePermissionNames(): array
    {
        return [
            'page_dinx_workspace_projects',
            'page_dinx_workspace_contracts',
            'page_dinx_workspace_invoices',
            'page_dinx_workspace_accounting',
            'page_dinx_workspace_reports',
            'page_dinx_workspace_settings',
        ];
    }
}
