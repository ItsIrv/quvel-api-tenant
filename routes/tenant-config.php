<?php

use Illuminate\Support\Facades\Route;
use Quvel\Tenant\Config\Http\Controllers\TenantConfigController;

/*
|--------------------------------------------------------------------------
| Tenant Config Routes
|--------------------------------------------------------------------------
|
| These routes provide tenant configuration access for SSR and frontend.
| Public endpoint requires tenant permission, protected requires internal middleware.
|
*/

Route::get('/public', [TenantConfigController::class, 'public'])
    ->name('tenant.config.public');

Route::get('/protected', [TenantConfigController::class, 'protected'])
    ->middleware(config('tenant.middleware.internal_request'))
    ->name('tenant.config.protected');

Route::get('/cache', [TenantConfigController::class, 'cache'])
    ->middleware(config('tenant.middleware.internal_request'))
    ->name('tenant.config.cache');