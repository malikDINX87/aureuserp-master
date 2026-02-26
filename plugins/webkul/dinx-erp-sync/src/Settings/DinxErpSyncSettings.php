<?php

namespace Webkul\DinxErpSync\Settings;

use Spatie\LaravelSettings\Settings;

class DinxErpSyncSettings extends Settings
{
    public ?bool $enabled;

    public ?string $webhook_secret;

    public ?int $max_timestamp_skew_seconds;

    public ?string $processing_queue;

    public ?bool $sso_enabled;

    public ?string $sso_shared_secret;

    public ?string $sso_issuer;

    public ?string $sso_audience;

    public ?int $sso_max_clock_skew_seconds;

    public ?int $sso_jti_ttl_seconds;

    public static function group(): string
    {
        return 'dinx_erp_sync';
    }
}
