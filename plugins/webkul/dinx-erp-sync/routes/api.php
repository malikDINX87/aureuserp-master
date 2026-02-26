<?php

use Illuminate\Support\Facades\Route;
use Webkul\DinxErpSync\Http\Controllers\API\V1\ClientConvertedWebhookController;

Route::name('dinx-erp-sync.api.v1.webhooks.')
    ->prefix('api/dinx/webhooks')
    ->middleware(['throttle:60,1'])
    ->group(function () {
        Route::post('client-converted', ClientConvertedWebhookController::class)
            ->name('client-converted');
    });
