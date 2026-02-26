<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('dinx_erp_sync.enabled', null);
        $this->migrator->add('dinx_erp_sync.webhook_secret', null);
        $this->migrator->add('dinx_erp_sync.max_timestamp_skew_seconds', 300);
        $this->migrator->add('dinx_erp_sync.processing_queue', null);
    }

    public function down(): void
    {
        $this->migrator->deleteIfExists('dinx_erp_sync.enabled');
        $this->migrator->deleteIfExists('dinx_erp_sync.webhook_secret');
        $this->migrator->deleteIfExists('dinx_erp_sync.max_timestamp_skew_seconds');
        $this->migrator->deleteIfExists('dinx_erp_sync.processing_queue');
    }
};
