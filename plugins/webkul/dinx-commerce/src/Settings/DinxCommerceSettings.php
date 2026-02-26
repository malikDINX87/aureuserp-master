<?php

namespace Webkul\DinxCommerce\Settings;

use Spatie\LaravelSettings\Settings;

class DinxCommerceSettings extends Settings
{
    public ?string $paypal_mode;

    public ?string $paypal_client_id;

    public ?string $paypal_client_secret;

    public ?string $paypal_webhook_id;

    public ?string $paypal_brand_name;

    public ?string $docusign_account_id;

    public ?string $docusign_integration_key;

    public ?string $docusign_user_id;

    public ?string $docusign_base_uri;

    public ?string $docusign_private_key_path;

    public ?string $docusign_webhook_secret;

    public static function group(): string
    {
        return 'dinx_commerce';
    }
}
