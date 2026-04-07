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
use App\Http\Controllers\Tenant\NotificationController;
use App\Http\Controllers\Tenant\CommissionController;
use App\Http\Controllers\Tenant\DashboardController;
use App\Http\Controllers\Tenant\SettingsController;
use App\Http\Controllers\Tenant\ReportController;
use App\Http\Controllers\Tenant\BranchController;
use App\Http\Controllers\Tenant\PackageController;
use App\Http\Controllers\Tenant\PrintController;
use App\Http\Controllers\Tenant\AdvanceController;
use App\Http\Controllers\Tenant\AppointmentPhotoController;
use App\Http\Controllers\Tenant\AppointmentDiagnosisController;
use App\Http\Controllers\Tenant\WarrantyController;
use App\Http\Controllers\Tenant\ExportController;
use App\Http\Controllers\Tenant\ExpenseController;
use App\Http\Controllers\Tenant\ExpenseCategoryController;
use App\Http\Controllers\Tenant\ClientHealthProfileController;
use App\Http\Controllers\Tenant\SessionNoteController;
use App\Http\Controllers\Tenant\AppointmentDetailController;
use App\Http\Controllers\Tenant\UnifiedNoteController;

Route::prefix('/salon/{tenant}')->middleware([
    'web',
    InitializeTenancyByPath::class,
    EnsureTenantIsActive::class,
    RedirectIfTrialExpired::class,
])->group(function () {
    Route::get('/', function () {
        return redirect()->route('tenant.dashboard', ['tenant' => tenant('id')]);
    });

    Route::get('/dashboard', [DashboardController::class, 'index'])
        ->middleware(['auth'])->name('tenant.dashboard');

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
        Route::get('agenda/pending-payments', [AppointmentController::class, 'pendingPayments'])->name('tenant.agenda.pending-payments');
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
        Route::get('ventas/nueva', [SaleController::class, 'create'])->name('tenant.sales.create');
        Route::get('ventas/summary', [SaleController::class, 'summary'])->name('tenant.sales.summary');
        Route::get('ventas/checkout-data', [SaleController::class, 'checkoutData'])->name('tenant.sales.checkout-data');
        Route::post('ventas', [SaleController::class, 'store'])->name('tenant.sales.store');
        Route::get('ventas/{sale}', [SaleController::class, 'show'])->name('tenant.sales.show');
        Route::post('ventas/{sale}/invoice', [SaleController::class, 'invoice'])->name('tenant.sales.invoice');
        Route::post('facturacion/{invoice}/retry', [SaleController::class, 'retryInvoice'])->name('tenant.invoices.retry');

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

        // Commissions
        Route::get('comisiones', [CommissionController::class, 'index'])->name('tenant.commissions.index');
        Route::get('comisiones/estilista/{stylist}', [CommissionController::class, 'stylist'])->name('tenant.commissions.stylist');
        Route::post('comisiones/pay', [CommissionController::class, 'pay'])->name('tenant.commissions.pay');

        // Notifications
        Route::get('notifications', [NotificationController::class, 'index'])->name('tenant.notifications.index');
        Route::get('notifications/unread', [NotificationController::class, 'unread'])->name('tenant.notifications.unread');
        Route::post('notifications/{id}/read', [NotificationController::class, 'markRead'])->name('tenant.notifications.read');
        Route::post('notifications/read-all', [NotificationController::class, 'markAllRead'])->name('tenant.notifications.read-all');

        // Settings
        Route::get('configuracion', [SettingsController::class, 'index'])->name('tenant.settings.index');
        Route::put('settings/salon', [SettingsController::class, 'updateSalon'])->name('tenant.settings.salon');
        Route::put('settings/appearance', [SettingsController::class, 'updateAppearance'])->name('tenant.settings.appearance');
        Route::put('settings/sri', [SettingsController::class, 'updateSri'])->name('tenant.settings.sri');
        Route::put('settings/sequential', [SettingsController::class, 'updateSequential'])->name('tenant.settings.sequential');
        Route::post('settings/certificate', [SettingsController::class, 'uploadCertificate'])->name('tenant.settings.certificate');
        Route::put('settings/schedule', [SettingsController::class, 'updateSchedule'])->name('tenant.settings.schedule');
        Route::put('settings/booking', [SettingsController::class, 'updateBooking'])->name('tenant.settings.booking');
        Route::put('settings/whatsapp', [SettingsController::class, 'updateWhatsapp'])->name('tenant.settings.whatsapp');
        Route::put('settings/printer', [SettingsController::class, 'updatePrinter'])->name('tenant.settings.printer');
        Route::put('settings/photos', [SettingsController::class, 'updatePhotos'])->name('tenant.settings.photos');
        Route::put('settings/payments', [SettingsController::class, 'updatePayments'])->name('tenant.settings.payments');
        Route::post('settings/invite', [SettingsController::class, 'inviteUser'])->name('tenant.settings.invite');
        Route::patch('settings/users/{user}/toggle', [SettingsController::class, 'toggleUser'])->name('tenant.settings.toggle-user');

        // Reports
        Route::get('reportes', [ReportController::class, 'index'])->name('tenant.reports.index');
        Route::get('reportes/revenue', [ReportController::class, 'revenue'])->name('tenant.reports.revenue');
        Route::get('reportes/services', [ReportController::class, 'services'])->name('tenant.reports.services');
        Route::get('reportes/stylists', [ReportController::class, 'stylists'])->name('tenant.reports.stylists');
        Route::get('reportes/clients', [ReportController::class, 'clients'])->name('tenant.reports.clients');
        Route::get('reportes/demand', [ReportController::class, 'demand'])->name('tenant.reports.demand');
        Route::get('reportes/inventory', [ReportController::class, 'inventory'])->name('tenant.reports.inventory');
        Route::get('reportes/forecast', [ReportController::class, 'forecast'])->name('tenant.reports.forecast');

        // Branches (multi-sucursal)
        Route::resource('sucursales', BranchController::class)
            ->parameters(['sucursales' => 'branch'])
            ->names([
                'index' => 'tenant.branches.index',
                'create' => 'tenant.branches.create',
                'store' => 'tenant.branches.store',
                'edit' => 'tenant.branches.edit',
                'update' => 'tenant.branches.update',
                'destroy' => 'tenant.branches.destroy',
            ]);
        Route::post('sucursales/switch', [BranchController::class, 'switchBranch'])->name('tenant.branches.switch');
        Route::post('sucursales/{branch}/certificate', [BranchController::class, 'uploadCertificate'])->name('tenant.branches.certificate');

        // Packages
        Route::resource('paquetes', PackageController::class)
            ->parameters(['paquetes' => 'package'])
            ->names([
                'index' => 'tenant.packages.index',
                'create' => 'tenant.packages.create',
                'store' => 'tenant.packages.store',
                'edit' => 'tenant.packages.edit',
                'update' => 'tenant.packages.update',
                'destroy' => 'tenant.packages.destroy',
            ]);
        Route::get('packages/check-client', [PackageController::class, 'checkClientPackage'])->name('tenant.packages.check');
        Route::get('packages/for-appointment', [PackageController::class, 'forAppointment'])->name('tenant.packages.for-appointment');
        Route::post('packages/use-session', [PackageController::class, 'useSession'])->name('tenant.packages.use-session');
        Route::get('packages/client/{clientId}', [PackageController::class, 'clientPackages'])->name('tenant.packages.client');

        // Appointment Photos
        Route::get('agenda/appointments/{appointment}/photos', [AppointmentPhotoController::class, 'index'])->name('tenant.appointment-photos.index');
        Route::post('agenda/appointments/{appointment}/photos', [AppointmentPhotoController::class, 'store'])->name('tenant.appointment-photos.store');
        Route::put('agenda/appointments/{appointment}/photos/{photo}', [AppointmentPhotoController::class, 'update'])->name('tenant.appointment-photos.update');
        Route::delete('agenda/appointments/{appointment}/photos/{photo}', [AppointmentPhotoController::class, 'destroy'])->name('tenant.appointment-photos.destroy');
        Route::get('clientes/{clientId}/photos', [AppointmentPhotoController::class, 'clientPhotos'])->name('tenant.client-photos');

        // Appointment Diagnosis
        Route::get('agenda/appointments/{appointment}/diagnosis', [AppointmentDiagnosisController::class, 'show'])->name('tenant.diagnosis.show');
        Route::post('agenda/appointments/{appointment}/diagnosis', [AppointmentDiagnosisController::class, 'store'])->name('tenant.diagnosis.store');
        Route::put('agenda/appointments/{appointment}/diagnosis', [AppointmentDiagnosisController::class, 'update'])->name('tenant.diagnosis.update');

        // Warranties
        Route::get('warranties/check', [WarrantyController::class, 'check'])->name('tenant.warranties.check');
        Route::get('warranties', [WarrantyController::class, 'index'])->name('tenant.warranties.index');
        Route::get('warranties/expiring', [WarrantyController::class, 'expiring'])->name('tenant.warranties.expiring');
        Route::get('warranties/client/{clientId}', [WarrantyController::class, 'clientWarranties'])->name('tenant.warranties.client');
        Route::post('warranties/{warranty}/void', [WarrantyController::class, 'void'])->name('tenant.warranties.void');

        // Client Health Profile
        Route::get('clientes/{client}/ficha-salud', [ClientHealthProfileController::class, 'show'])->name('tenant.health-profile.show');
        Route::put('clientes/{client}/ficha-salud', [ClientHealthProfileController::class, 'update'])->name('tenant.health-profile.update');
        Route::get('citas/{appointment}', [AppointmentDetailController::class, 'show'])->name('tenant.appointments.detail');
        Route::get('citas/{appointment}/alerta-salud', [ClientHealthProfileController::class, 'appointmentAlert'])->name('tenant.appointments.health-alert');
        Route::post('citas/{appointment}/confirmar-ficha', [ClientHealthProfileController::class, 'confirmReading'])->name('tenant.appointments.confirm-health');

        // Session Notes
        Route::get('citas/{appointment}/nota-sesion', [SessionNoteController::class, 'show'])->name('tenant.session-note.show');
        Route::post('citas/{appointment}/nota-sesion', [SessionNoteController::class, 'save'])->name('tenant.session-note.save');
        Route::post('citas/{appointment}/nota-unificada', [UnifiedNoteController::class, 'save'])->name('tenant.unified-note.save');

        // Gastos / Expenses (specific routes BEFORE parameterized ones)
        Route::get('gastos/api/pl', [ExpenseController::class, 'pl'])->name('tenant.expenses.pl');
        Route::get('gastos/consultar-sri', [ExpenseController::class, 'querySriInvoice'])->name('tenant.expenses.query-sri');
        Route::get('gastos/exportar', [ExpenseController::class, 'export'])->name('tenant.expenses.export');
        Route::get('gastos/categorias', [ExpenseCategoryController::class, 'index'])->name('tenant.expense-categories.index');
        Route::post('gastos/categorias', [ExpenseCategoryController::class, 'store'])->name('tenant.expense-categories.store');
        Route::put('gastos/categorias/{expenseCategory}', [ExpenseCategoryController::class, 'update'])->name('tenant.expense-categories.update');
        Route::delete('gastos/categorias/{expenseCategory}', [ExpenseCategoryController::class, 'destroy'])->name('tenant.expense-categories.destroy');
        Route::get('gastos', [ExpenseController::class, 'index'])->name('tenant.expenses.index');
        Route::post('gastos', [ExpenseController::class, 'store'])->name('tenant.expenses.store');
        Route::put('gastos/{expense}', [ExpenseController::class, 'update'])->name('tenant.expenses.update');
        Route::delete('gastos/{expense}', [ExpenseController::class, 'destroy'])->name('tenant.expenses.destroy');
        Route::get('gastos/{expense}/comprobante', [ExpenseController::class, 'downloadReceipt'])->name('tenant.expenses.receipt');

        // Exports / Excel
        Route::get('exports/sales', [ExportController::class, 'sales'])->name('tenant.exports.sales');
        Route::get('exports/profit', [ExportController::class, 'profit'])->name('tenant.exports.profit');
        Route::get('exports/appointments', [ExportController::class, 'appointments'])->name('tenant.exports.appointments');
        Route::get('exports/clients', [ExportController::class, 'clients'])->name('tenant.exports.clients');
        Route::get('exports/commissions', [ExportController::class, 'commissions'])->name('tenant.exports.commissions');
        Route::get('exports/inventory', [ExportController::class, 'inventory'])->name('tenant.exports.inventory');
        Route::get('exports/cashflow', [ExportController::class, 'cashflow'])->name('tenant.exports.cashflow');

        // Advances / Anticipos
        Route::get('advances', [AdvanceController::class, 'index'])->name('tenant.advances.index');
        Route::post('advances', [AdvanceController::class, 'store'])->name('tenant.advances.store');
        Route::get('advances/client/{clientId}', [AdvanceController::class, 'clientAdvances'])->name('tenant.advances.client');
        Route::post('advances/{advance}/apply', [AdvanceController::class, 'apply'])->name('tenant.advances.apply');
        Route::post('advances/{advance}/refund', [AdvanceController::class, 'refund'])->name('tenant.advances.refund');

        // Print / Tickets
        Route::get('print/sale/{sale}', [PrintController::class, 'sale'])->name('tenant.print.sale');
        Route::get('print/appointment/{appointment}', [PrintController::class, 'appointment'])->name('tenant.print.appointment');
        Route::get('print/closing/{date}', [PrintController::class, 'closing'])->name('tenant.print.closing');
        Route::get('print/invoice/{invoice}', [PrintController::class, 'invoice'])->name('tenant.print.invoice');
        Route::get('print/commission/{stylist}', [PrintController::class, 'commission'])->name('tenant.print.commission');
    });
});
