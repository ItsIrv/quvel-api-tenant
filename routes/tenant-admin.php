<?php

use Illuminate\Support\Facades\Route;
use Quvel\Tenant\Http\Controllers\TenantController;
use Quvel\Tenant\Models\Tenant;

/*
|--------------------------------------------------------------------------
| Tenant Admin Routes
|--------------------------------------------------------------------------
|
| These routes provide tenant management functionality. They should be
| protected by authentication and authorization middleware in production.
|
*/

Route::get('/', [TenantController::class, 'ui'])->name('ui');
Route::get('config-fields', [TenantController::class, 'configFields'])->name('config-fields');
Route::get('presets', [TenantController::class, 'presets'])->name('presets');
Route::get('presets/{preset}/fields', [TenantController::class, 'presetFields'])->name('presets.fields');
Route::get('tenants', [TenantController::class, 'index'])->name('index');
Route::post('tenants', [TenantController::class, 'store'])->name('store');
Route::put('tenants/{tenant}', [TenantController::class, 'update'])->name('update');
