<?php

use Illuminate\Support\Facades\Route;
use Webkul\DinxErpSync\Http\Controllers\Web\DinxSsoLoginController;

Route::middleware(['web', 'throttle:30,1'])
    ->group(function () {
        Route::get('/sso/dinx', DinxSsoLoginController::class)
            ->name('dinx-erp-sync.sso.dinx');
    });
