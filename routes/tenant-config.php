<?php

use Illuminate\Support\Facades\Route;
use Quvel\Tenant\Config\Http\Controllers\TenantConfigController;

/*
|--------------------------------------------------------------------------
| Tenant Config Routes
|--------------------------------------------------------------------------
|
| These routes provide tenant configuration access for SSR and frontend.
|
| /public - Can be called from any tenant domain (uses group middleware)
| /protected - Can be configured via middleware (uses group middleware)
| /cache - ALWAYS internal-only (hardcoded security check in controller)
|
*/

Route::get('/public', [TenantConfigController::class, 'public'])
    ->name('tenant.config.public');

Route::get('/protected', [TenantConfigController::class, 'protected'])
    ->middleware(config('tenant.middleware.internal_request'))
    ->name('tenant.config.protected');

Route::get('/cache', [TenantConfigController::class, 'cache'])
    ->name('tenant.config.cache');