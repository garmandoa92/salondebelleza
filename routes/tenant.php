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
use App\Http\Controllers\Tenant\AppointmentController;
use App\Http\Controllers\Tenant\BookingController;
use App\Http\Controllers\Tenant\ClientController;
use App\Http\Controllers\Tenant\SaleController;
use App\Http\Controllers\Tenant\SriInvoiceController;
use App\Http\Controllers\Tenant\ProductController;
use App\Http\Controllers\Tenant\StockMovementController;

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

    // Public booking routes (no auth required)
    Route::get('/reservar', [BookingController::class, 'index'])->name('tenant.booking');
    Route::get('/reservar/services', [BookingController::class, 'services'])->name('tenant.booking.services');
    Route::get('/reservar/stylists', [BookingController::class, 'stylists'])->name('tenant.booking.stylists');
    Route::get('/reservar/availability', [BookingController::class, 'availability'])->name('tenant.booking.availability');
    Route::post('/reservar/appointments', [BookingController::class, 'store'])->name('tenant.booking.store');
    Route::get('/reservar/confirm/{token}', [BookingController::class, 'confirm'])->name('tenant.booking.confirm');
    Route::get('/reservar/cancel/{token}', [BookingController::class, 'cancel'])->name('tenant.booking.cancel');

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

        // Agenda / Appointments
        Route::get('agenda', [AppointmentController::class, 'index'])->name('tenant.agenda.index');
        Route::get('agenda/events', [AppointmentController::class, 'events'])->name('tenant.agenda.events');
        Route::get('agenda/occupancy', [AppointmentController::class, 'occupancy'])->name('tenant.agenda.occupancy');
        Route::get('agenda/availability', [AppointmentController::class, 'availability'])->name('tenant.agenda.availability');
        Route::get('agenda/search-clients', [AppointmentController::class, 'searchClients'])->name('tenant.agenda.search-clients');
        Route::post('agenda/store-client', [AppointmentController::class, 'storeClient'])->name('tenant.agenda.store-client');
        Route::post('agenda/appointments', [AppointmentController::class, 'store'])->name('tenant.appointments.store');
        Route::get('agenda/appointments/{appointment}', [AppointmentController::class, 'show'])->name('tenant.appointments.show');
        Route::put('agenda/appointments/{appointment}', [AppointmentController::class, 'update'])->name('tenant.appointments.update');
        Route::delete('agenda/appointments/{appointment}', [AppointmentController::class, 'destroy'])->name('tenant.appointments.destroy');
        Route::post('agenda/appointments/{appointment}/confirm', [AppointmentController::class, 'confirm'])->name('tenant.appointments.confirm');
        Route::post('agenda/appointments/{appointment}/start', [AppointmentController::class, 'start'])->name('tenant.appointments.start');
        Route::post('agenda/appointments/{appointment}/complete', [AppointmentController::class, 'complete'])->name('tenant.appointments.complete');
        Route::post('agenda/appointments/{appointment}/no-show', [AppointmentController::class, 'noShow'])->name('tenant.appointments.no-show');

        // Clients CRM
        Route::resource('clientes', ClientController::class)
            ->parameters(['clientes' => 'client'])
            ->names([
                'index' => 'tenant.clients.index',
                'create' => 'tenant.clients.create',
                'store' => 'tenant.clients.store',
                'show' => 'tenant.clients.show',
                'edit' => 'tenant.clients.edit',
                'update' => 'tenant.clients.update',
                'destroy' => 'tenant.clients.destroy',
            ]);

        // Sales / Checkout
        Route::get('ventas', [SaleController::class, 'index'])->name('tenant.sales.index');
        Route::get('ventas/summary', [SaleController::class, 'summary'])->name('tenant.sales.summary');
        Route::get('ventas/checkout-data', [SaleController::class, 'checkoutData'])->name('tenant.sales.checkout-data');
        Route::post('ventas', [SaleController::class, 'store'])->name('tenant.sales.store');
        Route::get('ventas/{sale}', [SaleController::class, 'show'])->name('tenant.sales.show');
        Route::post('ventas/{sale}/invoice', [SaleController::class, 'invoice'])->name('tenant.sales.invoice');

        // SRI Invoices
        Route::get('facturacion', [SriInvoiceController::class, 'index'])->name('tenant.invoices.index');
        Route::get('facturacion/{invoice}', [SriInvoiceController::class, 'show'])->name('tenant.invoices.show');
        Route::get('facturacion/{invoice}/ride', [SriInvoiceController::class, 'ride'])->name('tenant.invoices.ride');
        Route::get('facturacion/{invoice}/xml', [SriInvoiceController::class, 'xml'])->name('tenant.invoices.xml');
        Route::post('facturacion/{invoice}/retry', [SriInvoiceController::class, 'retry'])->name('tenant.invoices.retry');

        // Products / Inventory
        Route::resource('inventario', ProductController::class)
            ->parameters(['inventario' => 'product'])
            ->names([
                'index' => 'tenant.products.index',
                'create' => 'tenant.products.create',
                'store' => 'tenant.products.store',
                'edit' => 'tenant.products.edit',
                'update' => 'tenant.products.update',
                'destroy' => 'tenant.products.destroy',
            ]);

        // Stock Movements
        Route::get('stock/movements', [StockMovementController::class, 'index'])->name('tenant.stock.movements');
        Route::post('stock/purchase', [StockMovementController::class, 'purchase'])->name('tenant.stock.purchase');
        Route::post('stock/adjustment', [StockMovementController::class, 'adjustment'])->name('tenant.stock.adjustment');
    });
});
