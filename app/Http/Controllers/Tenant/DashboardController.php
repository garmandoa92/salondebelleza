<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\Client;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SriInvoice;
use App\Models\Stylist;
use Carbon\Carbon;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function index()
    {
        $today = Carbon::today();
        $yesterday = Carbon::yesterday();
        $monthStart = Carbon::now()->startOfMonth();
        $lastMonthStart = Carbon::now()->subMonth()->startOfMonth();
        $lastMonthEnd = Carbon::now()->subMonth()->endOfMonth();
        $branchId = session('current_branch_id');

        // KPIs - Today (filtered by branch if selected)
        $todaySales = Sale::where('status', 'completed')->whereDate('completed_at', $today)->when($branchId, fn ($q) => $q->where('branch_id', $branchId));
        $yesterdaySales = Sale::where('status', 'completed')->whereDate('completed_at', $yesterday)->when($branchId, fn ($q) => $q->where('branch_id', $branchId));
        $revenueToday = (clone $todaySales)->sum('total');
        $revenueYesterday = (clone $yesterdaySales)->sum('total');

        $todayAppointments = Appointment::whereDate('starts_at', $today)->when($branchId, fn ($q) => $q->where('branch_id', $branchId));
        $appointmentsTotal = (clone $todayAppointments)->count();
        $appointmentsCompleted = (clone $todayAppointments)->where('status', 'completed')->count();
        $appointmentsPending = (clone $todayAppointments)->whereIn('status', ['pending', 'confirmed'])->count();
        $appointmentsCancelled = (clone $todayAppointments)->where('status', 'cancelled')->count();

        $clientsToday = Appointment::whereDate('starts_at', $today)
            ->where('status', 'completed')
            ->distinct('client_id')
            ->count('client_id');

        // Occupancy
        $activeStylists = Stylist::where('is_active', true)->count();
        $totalWorkHours = $activeStylists * 9; // ~9 hours per stylist
        $bookedHours = Appointment::whereDate('starts_at', $today)
            ->whereNotIn('status', ['cancelled'])
            ->get()
            ->sum(fn ($a) => $a->starts_at->diffInMinutes($a->ends_at) / 60);
        $occupancy = $totalWorkHours > 0 ? min(100, round($bookedHours / $totalWorkHours * 100)) : 0;

        // Today's appointments by stylist
        $todayAgenda = Stylist::where('is_active', true)
            ->with(['appointments' => function ($q) use ($today) {
                $q->whereDate('starts_at', $today)
                    ->whereNotIn('status', ['cancelled'])
                    ->with(['client:id,first_name,last_name', 'service:id,name'])
                    ->orderBy('starts_at');
            }])
            ->get(['id', 'name', 'color']);

        // Month metrics
        $monthRevenue = Sale::where('status', 'completed')
            ->whereBetween('completed_at', [$monthStart, now()])
            ->sum('total');
        $lastMonthRevenue = Sale::where('status', 'completed')
            ->whereBetween('completed_at', [$lastMonthStart, $lastMonthEnd])
            ->sum('total');

        // Top services this month
        $topServices = \App\Models\SaleItem::where('type', 'service')
            ->whereHas('sale', fn ($q) => $q->where('status', 'completed')->whereBetween('completed_at', [$monthStart, now()]))
            ->selectRaw('name, COUNT(*) as count, SUM(subtotal) as total')
            ->groupBy('name')
            ->orderByDesc('count')
            ->limit(5)
            ->get();

        // Alerts
        $lowStockProducts = Product::where('is_active', true)
            ->whereColumn('stock', '<=', 'min_stock')
            ->where('min_stock', '>', 0)
            ->get(['id', 'name', 'stock', 'min_stock', 'unit']);

        $rejectedInvoices = SriInvoice::where('sri_status', 'rejected')
            ->where('retry_count', '<', 3)
            ->count();

        $unconfirmedAppointments = Appointment::whereDate('starts_at', $today)
            ->where('status', 'pending')
            ->where('starts_at', '<', now()->subHour())
            ->count();

        $inactiveClients = Client::where(function ($q) {
            $q->where('last_visit_at', '<', now()->subDays(60))->orWhereNull('last_visit_at');
        })->where('is_active', true)->count();

        return Inertia::render('Tenant/Dashboard', [
            'kpis' => [
                'revenue_today' => round((float) $revenueToday, 2),
                'revenue_yesterday' => round((float) $revenueYesterday, 2),
                'appointments_total' => $appointmentsTotal,
                'appointments_completed' => $appointmentsCompleted,
                'appointments_pending' => $appointmentsPending,
                'appointments_cancelled' => $appointmentsCancelled,
                'clients_today' => $clientsToday,
                'occupancy' => $occupancy,
            ],
            'today_agenda' => $todayAgenda,
            'month' => [
                'revenue' => round((float) $monthRevenue, 2),
                'last_month_revenue' => round((float) $lastMonthRevenue, 2),
                'top_services' => $topServices,
            ],
            'alerts' => [
                'low_stock' => $lowStockProducts,
                'rejected_invoices' => $rejectedInvoices,
                'unconfirmed' => $unconfirmedAppointments,
                'inactive_clients' => $inactiveClients,
            ],
        ]);
    }
}
