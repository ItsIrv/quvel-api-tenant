<?php

use Illuminate\Support\Facades\Route;
use Quvel\Tenant\Http\Controllers\TenantConfigController;

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
    ->middleware('tenant.internal')
    ->name('tenant.config.protected');

Route::get('/cache', [TenantConfigController::class, 'cache'])
    ->middleware('tenant.internal')
    ->name('tenant.config.cache');