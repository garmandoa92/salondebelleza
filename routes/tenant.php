<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Stancl\Tenancy\Middleware\InitializeTenancyByPath;
use App\Http\Middleware\EnsureTenantIsActive;
use App\Http\Middleware\RedirectIfTrialExpired;

Route::prefix('/salon/{tenant}')->middleware([
    'web',
    InitializeTenancyByPath::class,
    EnsureTenantIsActive::class,
    RedirectIfTrialExpired::class,
])->group(function () {
    Route::get('/', function () {
        return redirect()->route('tenant.dashboard', ['tenant' => tenant('id')]);
    });

    Route::get('/dashboard', function () {
        return inertia('Tenant/Dashboard', [
            'tenant' => tenant(),
        ]);
    })->middleware(['auth'])->name('tenant.dashboard');

    Route::get('/upgrade', function () {
        return inertia('Tenant/Upgrade', [
            'tenant' => tenant(),
        ]);
    })->name('tenant.upgrade');

    // Tenant auth routes
    Route::get('/login', [\App\Http\Controllers\Tenant\AuthController::class, 'showLogin'])
        ->name('tenant.login');
    Route::post('/login', [\App\Http\Controllers\Tenant\AuthController::class, 'login'])
        ->middleware('guest')
        ->name('tenant.login.post');

    Route::post('/logout', [\App\Http\Controllers\Tenant\AuthController::class, 'logout'])
        ->middleware('auth')
        ->name('tenant.logout');
});
