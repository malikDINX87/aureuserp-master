<?php

namespace Webkul\DinxCommerce;

use Filament\Panel;
use Webkul\DinxCommerce\Console\Commands\InstallDinxCommerceCommand;
use Webkul\DinxCommerce\Console\Commands\RunRecurringInvoicesCommand;
use Webkul\PluginManager\Console\Commands\UninstallCommand;
use Webkul\PluginManager\Package;
use Webkul\PluginManager\PackageServiceProvider;

class DinxCommerceServiceProvider extends PackageServiceProvider
{
    public static string $name = 'dinx-commerce';

    public function configureCustomPackage(Package $package): void
    {
        $package->name(static::$name)
            ->hasViews()
            ->hasRoutes(['api'])
            ->hasMigrations([
                '2026_02_24_100001_create_dinx_contracts_table',
                '2026_02_24_100002_create_dinx_contract_invoice_links_table',
                '2026_02_24_100003_create_dinx_contract_payment_links_table',
                '2026_02_24_100004_create_dinx_contract_events_table',
                '2026_02_24_100005_create_dinx_paypal_orders_table',
                '2026_02_26_000001_create_dinx_project_invoice_links_table',
                '2026_02_26_000002_create_dinx_contract_versions_table',
                '2026_02_26_000003_create_dinx_recurring_invoice_profiles_table',
                '2026_02_26_000004_create_dinx_bank_statement_imports_table',
                '2026_02_26_000005_create_dinx_bank_statement_lines_table',
                '2026_02_26_000006_create_dinx_bank_reconciliation_matches_table',
                '2026_02_26_000007_create_dinx_expense_category_rules_table',
                '2026_02_26_000008_create_dinx_tax_mapper_rules_table',
                '2026_02_26_000009_create_dinx_report_favorites_table',
            ])
            ->runsMigrations()
            ->hasSettings([
                '2026_02_24_100006_create_dinx_commerce_settings',
                '2026_02_26_000010_create_dinx_workspace_settings',
            ])
            ->runsSettings()
            ->hasDependencies([
                'accounts',
                'partners',
                'security',
                'projects',
                'accounting',
                'invoices',
                'timesheets',
            ])
            ->hasCommand(InstallDinxCommerceCommand::class)
            ->hasCommand(RunRecurringInvoicesCommand::class)
            ->hasUninstallCommand(function (UninstallCommand $command) {})
            ->icon('accounting');
    }

    public function packageBooted(): void
    {
        //
    }

    public function packageRegistered(): void
    {
        Panel::configureUsing(function (Panel $panel): void {
            $panel->plugin(DinxCommercePlugin::make());
        });
    }
}
