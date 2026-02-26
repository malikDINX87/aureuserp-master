<?php

use Webkul\DinxErpSync\Filament\Admin\Resources\DinxSyncLogResource;

return [
    'resources' => [
        'manage' => [
            DinxSyncLogResource::class => ['view_any', 'view'],
        ],
        'exclude' => [],
    ],

    'pages' => [
        'exclude' => [],
    ],
];
