<?php

use Webkul\DinxCommerce\Filament\Admin\Resources\DinxContractResource;

return [
    'resources' => [
        'manage' => [
            DinxContractResource::class => ['view_any', 'view', 'create', 'update'],
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
