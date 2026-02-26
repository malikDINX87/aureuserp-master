<?php

use Spatie\LaravelSettings\Exceptions\SettingAlreadyExists;
use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->addSettingIfMissing('dinx_erp_sync.enabled', null);
        $this->addSettingIfMissing('dinx_erp_sync.webhook_secret', null);
        $this->addSettingIfMissing('dinx_erp_sync.max_timestamp_skew_seconds', 300);
        $this->addSettingIfMissing('dinx_erp_sync.processing_queue', null);
    }

    public function down(): void
    {
        $this->migrator->deleteIfExists('dinx_erp_sync.enabled');
        $this->migrator->deleteIfExists('dinx_erp_sync.webhook_secret');
        $this->migrator->deleteIfExists('dinx_erp_sync.max_timestamp_skew_seconds');
        $this->migrator->deleteIfExists('dinx_erp_sync.processing_queue');
    }

    protected function addSettingIfMissing(string $key, mixed $value): void
    {
        try {
            $this->migrator->add($key, $value);
        } catch (SettingAlreadyExists) {
            // no-op: setting already exists on repeated install
        }
    }
};
