<?php

use Spatie\LaravelSettings\Exceptions\SettingAlreadyExists;
use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->addSettingIfMissing('dinx_commerce.paypal_mode', 'sandbox');
        $this->addSettingIfMissing('dinx_commerce.paypal_client_id', null);
        $this->addSettingIfMissing('dinx_commerce.paypal_client_secret', null);
        $this->addSettingIfMissing('dinx_commerce.paypal_webhook_id', null);
        $this->addSettingIfMissing('dinx_commerce.paypal_brand_name', 'DINX');

        $this->addSettingIfMissing('dinx_commerce.docusign_account_id', null);
        $this->addSettingIfMissing('dinx_commerce.docusign_integration_key', null);
        $this->addSettingIfMissing('dinx_commerce.docusign_user_id', null);
        $this->addSettingIfMissing('dinx_commerce.docusign_base_uri', null);
        $this->addSettingIfMissing('dinx_commerce.docusign_private_key_path', null);
        $this->addSettingIfMissing('dinx_commerce.docusign_webhook_secret', null);
    }

    public function down(): void
    {
        $this->migrator->deleteIfExists('dinx_commerce.paypal_mode');
        $this->migrator->deleteIfExists('dinx_commerce.paypal_client_id');
        $this->migrator->deleteIfExists('dinx_commerce.paypal_client_secret');
        $this->migrator->deleteIfExists('dinx_commerce.paypal_webhook_id');
        $this->migrator->deleteIfExists('dinx_commerce.paypal_brand_name');

        $this->migrator->deleteIfExists('dinx_commerce.docusign_account_id');
        $this->migrator->deleteIfExists('dinx_commerce.docusign_integration_key');
        $this->migrator->deleteIfExists('dinx_commerce.docusign_user_id');
        $this->migrator->deleteIfExists('dinx_commerce.docusign_base_uri');
        $this->migrator->deleteIfExists('dinx_commerce.docusign_private_key_path');
        $this->migrator->deleteIfExists('dinx_commerce.docusign_webhook_secret');
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
