<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Stancl\Tenancy\Middleware\InitializeTenancyBySubdomain;
use Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains;
use App\Http\Middleware\EnsureTenantIsActive;
use App\Http\Middleware\RedirectIfTrialExpired;

Route::middleware([
    'web',
    InitializeTenancyBySubdomain::class,
    PreventAccessFromCentralDomains::class,
    EnsureTenantIsActive::class,
    RedirectIfTrialExpired::class,
])->group(function () {
    Route::get('/', function () {
        return redirect()->route('tenant.dashboard');
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
    Route::middleware('guest')->group(function () {
        Route::get('/login', [\App\Http\Controllers\Tenant\AuthController::class, 'showLogin'])
            ->name('tenant.login');
        Route::post('/login', [\App\Http\Controllers\Tenant\AuthController::class, 'login']);
    });

    Route::post('/logout', [\App\Http\Controllers\Tenant\AuthController::class, 'logout'])
        ->middleware('auth')
        ->name('tenant.logout');
});
