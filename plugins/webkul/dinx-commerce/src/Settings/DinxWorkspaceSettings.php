<?php

namespace Webkul\DinxCommerce\Settings;

use Spatie\LaravelSettings\Settings;

class DinxWorkspaceSettings extends Settings
{
    public float|int $project_default_billable_hourly_rate;

    public float|int $project_default_cost_hourly_rate;

    public ?string $crm_client_url_template;

    public ?string $brand_logo_path;

    public ?string $brand_primary_hex;

    public ?string $brand_secondary_hex;

    public bool $notify_invoice_paid;

    public bool $notify_contract_signed;

    public static function group(): string
    {
        return 'dinx_workspace';
    }
}
