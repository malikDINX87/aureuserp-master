<?php

use Spatie\LaravelSettings\Exceptions\SettingAlreadyExists;
use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->addSettingIfMissing('dinx_erp_sync.sso_enabled', null);
        $this->addSettingIfMissing('dinx_erp_sync.sso_shared_secret', null);
        $this->addSettingIfMissing('dinx_erp_sync.sso_issuer', 'dinxsolutions.com');
        $this->addSettingIfMissing('dinx_erp_sync.sso_audience', 'dinx-erp');
        $this->addSettingIfMissing('dinx_erp_sync.sso_max_clock_skew_seconds', 60);
        $this->addSettingIfMissing('dinx_erp_sync.sso_jti_ttl_seconds', 600);
    }

    public function down(): void
    {
        $this->migrator->deleteIfExists('dinx_erp_sync.sso_enabled');
        $this->migrator->deleteIfExists('dinx_erp_sync.sso_shared_secret');
        $this->migrator->deleteIfExists('dinx_erp_sync.sso_issuer');
        $this->migrator->deleteIfExists('dinx_erp_sync.sso_audience');
        $this->migrator->deleteIfExists('dinx_erp_sync.sso_max_clock_skew_seconds');
        $this->migrator->deleteIfExists('dinx_erp_sync.sso_jti_ttl_seconds');
    }

    protected function addSettingIfMissing(string $key, mixed $value): void
    {
        try {
            $this->migrator->add($key, $value);
        } catch (SettingAlreadyExists) {
            // no-op: make repeated install runs idempotent
        }
    }
};
