<?php

use Spatie\LaravelSettings\Exceptions\SettingAlreadyExists;
use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->addSettingIfMissing('dinx_workspace.project_default_billable_hourly_rate', 150);
        $this->addSettingIfMissing('dinx_workspace.project_default_cost_hourly_rate', 75);
        $this->addSettingIfMissing('dinx_workspace.crm_client_url_template', 'https://dinxsolutions.com/dashboard/apps/dinx-crm/hit-list?lead={lead_id}');
        $this->addSettingIfMissing('dinx_workspace.brand_logo_path', 'images/dinx-logo.png');
        $this->addSettingIfMissing('dinx_workspace.brand_primary_hex', '#004AAD');
        $this->addSettingIfMissing('dinx_workspace.brand_secondary_hex', '#0F172A');
        $this->addSettingIfMissing('dinx_workspace.notify_invoice_paid', true);
        $this->addSettingIfMissing('dinx_workspace.notify_contract_signed', true);
    }

    public function down(): void
    {
        $this->migrator->deleteIfExists('dinx_workspace.project_default_billable_hourly_rate');
        $this->migrator->deleteIfExists('dinx_workspace.project_default_cost_hourly_rate');
        $this->migrator->deleteIfExists('dinx_workspace.crm_client_url_template');
        $this->migrator->deleteIfExists('dinx_workspace.brand_logo_path');
        $this->migrator->deleteIfExists('dinx_workspace.brand_primary_hex');
        $this->migrator->deleteIfExists('dinx_workspace.brand_secondary_hex');
        $this->migrator->deleteIfExists('dinx_workspace.notify_invoice_paid');
        $this->migrator->deleteIfExists('dinx_workspace.notify_contract_signed');
    }

    protected function addSettingIfMissing(string $key, mixed $value): void
    {
        try {
            $this->migrator->add($key, $value);
        } catch (SettingAlreadyExists) {
            // no-op for repeat installs
        }
    }
};
