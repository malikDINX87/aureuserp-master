<?php

use Illuminate\Support\Facades\Route;
use Webkul\DinxCommerce\Http\Controllers\API\V1\DocuSignWebhookController;
use Webkul\DinxCommerce\Http\Controllers\API\V1\PayPalWebhookController;

Route::middleware(['throttle:120,1'])
    ->prefix('api/dinx')
    ->name('dinx-commerce.api.v1.')
    ->group(function () {
        Route::post('/paypal/webhook', PayPalWebhookController::class)
            ->name('paypal.webhook');

        Route::post('/docusign/webhook', DocuSignWebhookController::class)
            ->name('docusign.webhook');
    });
