<?php

use Illuminate\Support\Facades\Route;
use Quvel\Tenant\Admin\Http\Controllers\TenantAdminController;

/*
|--------------------------------------------------------------------------
| Tenant Admin Routes
|--------------------------------------------------------------------------
|
| These routes provide tenant management functionality. They should be
| protected by authentication and authorization middleware in production.
|
*/

Route::get('/', [TenantAdminController::class, 'showUI'])->name('ui');
Route::get('config-fields', [TenantAdminController::class, 'configFields'])->name('config-fields');
Route::get('presets', [TenantAdminController::class, 'presets'])->name('presets');
Route::get('presets/{preset}/fields', [TenantAdminController::class, 'presetFields'])->name('presets.fields');
Route::get('tenants', [TenantAdminController::class, 'index'])->name('index');
Route::post('tenants', [TenantAdminController::class, 'store'])->name('store');
Route::put('tenants/{tenant}', [TenantAdminController::class, 'update'])->name('update');
