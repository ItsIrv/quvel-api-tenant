<?php

use Illuminate\Support\Facades\Route;
use Quvel\Tenant\Http\Controllers\TenantController;

/*
|--------------------------------------------------------------------------
| Tenant Admin Routes
|--------------------------------------------------------------------------
|
| These routes provide tenant management functionality. They should be
| protected by authentication and authorization middleware in production.
|
*/

Route::prefix('admin/tenants')->name('tenant.admin.')->group(function () {
    Route::get('ui', [TenantController::class, 'ui'])->name('ui');
    Route::get('presets', [TenantController::class, 'presets'])->name('presets');
    Route::get('presets/{preset}/fields', [TenantController::class, 'presetFields'])->name('presets.fields');
    Route::get('/', [TenantController::class, 'index'])->name('index');
    Route::post('/', [TenantController::class, 'store'])->name('store');
});