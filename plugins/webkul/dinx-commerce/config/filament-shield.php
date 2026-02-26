<?php

use Webkul\DinxCommerce\Filament\Admin\Resources\DinxContractResource;
use Webkul\DinxCommerce\Filament\Admin\Resources\DinxPayPalOrderResource;

return [
    'resources' => [
        'manage' => [
            DinxContractResource::class => ['view_any', 'view', 'create', 'update'],
            DinxPayPalOrderResource::class => ['view_any', 'view'],
        ],
        'exclude' => [],
    ],

    'pages' => [
        'exclude' => [],
    ],

    'custom_permissions' => [
        'page_dinx_workspace_projects',
        'page_dinx_workspace_contracts',
        'page_dinx_workspace_invoices',
        'page_dinx_workspace_accounting',
        'page_dinx_workspace_reports',
        'page_dinx_workspace_settings',
    ],
];
