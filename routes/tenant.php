<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Stancl\Tenancy\Middleware\InitializeTenancyByPath;
use App\Http\Middleware\EnsureTenantIsActive;
use App\Http\Middleware\RedirectIfTrialExpired;
use App\Http\Controllers\Tenant\AuthController;
use App\Http\Controllers\Tenant\ServiceController;
use App\Http\Controllers\Tenant\ServiceCategoryController;
use App\Http\Controllers\Tenant\StylistController;
use App\Http\Controllers\Tenant\BlockedTimeController;

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

    // Auth routes
    Route::get('/login', [AuthController::class, 'showLogin'])->name('tenant.login');
    Route::post('/login', [AuthController::class, 'login'])->middleware('guest')->name('tenant.login.post');
    Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth')->name('tenant.logout');

    // Authenticated routes
    Route::middleware(['auth'])->group(function () {
        // Services
        Route::resource('servicios', ServiceController::class)
            ->parameters(['servicios' => 'service'])
            ->names([
                'index' => 'tenant.services.index',
                'create' => 'tenant.services.create',
                'store' => 'tenant.services.store',
                'edit' => 'tenant.services.edit',
                'update' => 'tenant.services.update',
                'destroy' => 'tenant.services.destroy',
            ]);
        Route::patch('servicios/{service}/toggle', [ServiceController::class, 'toggleActive'])->name('tenant.services.toggle');
        Route::post('servicios/reorder', [ServiceController::class, 'reorder'])->name('tenant.services.reorder');

        // Service Categories
        Route::post('categorias', [ServiceCategoryController::class, 'store'])->name('tenant.categories.store');
        Route::put('categorias/{category}', [ServiceCategoryController::class, 'update'])->name('tenant.categories.update');
        Route::delete('categorias/{category}', [ServiceCategoryController::class, 'destroy'])->name('tenant.categories.destroy');

        // Stylists
        Route::resource('estilistas', StylistController::class)
            ->parameters(['estilistas' => 'stylist'])
            ->names([
                'index' => 'tenant.stylists.index',
                'create' => 'tenant.stylists.create',
                'store' => 'tenant.stylists.store',
                'edit' => 'tenant.stylists.edit',
                'update' => 'tenant.stylists.update',
                'destroy' => 'tenant.stylists.destroy',
            ]);
        Route::patch('estilistas/{stylist}/toggle', [StylistController::class, 'toggleActive'])->name('tenant.stylists.toggle');
        Route::get('estilistas/{stylist}/horario', [StylistController::class, 'schedule'])->name('tenant.stylists.schedule');

        // Blocked Times
        Route::post('bloqueos', [BlockedTimeController::class, 'store'])->name('tenant.blocked-times.store');
        Route::delete('bloqueos/{blockedTime}', [BlockedTimeController::class, 'destroy'])->name('tenant.blocked-times.destroy');
    });
});
