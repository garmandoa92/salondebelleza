<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\Client;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\StockMovement;
use App\Models\Stylist;
use Carbon\Carbon;
use Carbon\CarbonPeriod;

class ReportService
{
    public function getRevenueReport(string $from, string $to): array
    {
        $start = Carbon::parse($from)->startOfDay();
        $end = Carbon::parse($to)->endOfDay();
        $days = $start->diffInDays($end) + 1;
        $prevStart = $start->copy()->subDays($days);
        $prevEnd = $start->copy()->subDay()->endOfDay();

        $sales = Sale::where('status', 'completed')->whereBetween('completed_at', [$start, $end]);
        $prevSales = Sale::where('status', 'completed')->whereBetween('completed_at', [$prevStart, $prevEnd]);

        $total = (clone $sales)->sum('total');
        $prevTotal = (clone $prevSales)->sum('total');
        $count = (clone $sales)->count();
        $avgTicket = $count > 0 ? round((float) $total / $count, 2) : 0;

        // Daily breakdown
        $daily = [];
        foreach (CarbonPeriod::create($start, $end) as $day) {
            $dayTotal = Sale::where('status', 'completed')
                ->whereDate('completed_at', $day)->sum('total');
            $daily[] = ['date' => $day->format('Y-m-d'), 'label' => $day->format('d M'), 'total' => round((float) $dayTotal, 2)];
        }

        // By payment method
        $allSales = (clone $sales)->get();
        $byMethod = ['cash' => 0, 'transfer' => 0, 'card_debit' => 0, 'card_credit' => 0, 'other' => 0];
        foreach ($allSales as $s) {
            foreach ($s->payment_methods ?? [] as $p) {
                $method = $p['method'] ?? 'other';
                $byMethod[$method] = ($byMethod[$method] ?? 0) + (float) ($p['amount'] ?? 0);
            }
        }

        // By type
        $serviceRevenue = SaleItem::where('type', 'service')
            ->whereHas('sale', fn ($q) => $q->where('status', 'completed')->whereBetween('completed_at', [$start, $end]))
            ->sum('subtotal');
        $productRevenue = SaleItem::where('type', 'product')
            ->whereHas('sale', fn ($q) => $q->where('status', 'completed')->whereBetween('completed_at', [$start, $end]))
            ->sum('subtotal');

        return [
            'total' => round((float) $total, 2),
            'prev_total' => round((float) $prevTotal, 2),
            'growth' => $prevTotal > 0 ? round(($total - $prevTotal) / $prevTotal * 100, 1) : 0,
            'count' => $count,
            'avg_ticket' => $avgTicket,
            'daily' => $daily,
            'by_method' => $byMethod,
            'service_revenue' => round((float) $serviceRevenue, 2),
            'product_revenue' => round((float) $productRevenue, 2),
        ];
    }

    public function getServicesReport(string $from, string $to): array
    {
        $start = Carbon::parse($from)->startOfDay();
        $end = Carbon::parse($to)->endOfDay();

        $items = SaleItem::where('type', 'service')
            ->whereHas('sale', fn ($q) => $q->where('status', 'completed')->whereBetween('completed_at', [$start, $end]))
            ->selectRaw('name, COUNT(*) as count, SUM(subtotal) as total_revenue')
            ->groupBy('name')
            ->orderByDesc('count')
            ->limit(10)
            ->get();

        $appointments = Appointment::whereBetween('starts_at', [$start, $end]);
        $noShowRate = (clone $appointments)->count() > 0
            ? round((clone $appointments)->where('status', 'no_show')->count() / (clone $appointments)->count() * 100, 1)
            : 0;

        return [
            'top_services' => $items->map(fn ($i) => [
                'name' => $i->name,
                'count' => $i->count,
                'revenue' => round((float) $i->total_revenue, 2),
            ])->toArray(),
            'no_show_rate' => $noShowRate,
        ];
    }

    public function getStylistsReport(string $from, string $to): array
    {
        $start = Carbon::parse($from)->startOfDay();
        $end = Carbon::parse($to)->endOfDay();

        $stylists = Stylist::where('is_active', true)->get();

        return $stylists->map(function (Stylist $s) use ($start, $end) {
            $appointments = Appointment::where('stylist_id', $s->id)
                ->whereBetween('starts_at', [$start, $end]);

            $completed = (clone $appointments)->where('status', 'completed')->count();
            $total = (clone $appointments)->count();

            $revenue = SaleItem::where('stylist_id', $s->id)
                ->whereHas('sale', fn ($q) => $q->where('status', 'completed')->whereBetween('completed_at', [$start, $end]))
                ->sum('subtotal');

            $completionRate = $total > 0 ? round($completed / $total * 100, 1) : 0;

            return [
                'id' => $s->id,
                'name' => $s->name,
                'color' => $s->color,
                'revenue' => round((float) $revenue, 2),
                'services_count' => $completed,
                'total_appointments' => $total,
                'completion_rate' => $completionRate,
                'avg_ticket' => $completed > 0 ? round((float) $revenue / $completed, 2) : 0,
            ];
        })->sortByDesc('revenue')->values()->toArray();
    }

    public function getClientsReport(string $from, string $to): array
    {
        $start = Carbon::parse($from)->startOfDay();
        $end = Carbon::parse($to)->endOfDay();

        $newClients = Client::whereBetween('created_at', [$start, $end])->count();

        $clientsWithAppointments = Appointment::whereBetween('starts_at', [$start, $end])
            ->where('status', 'completed')
            ->distinct('client_id')
            ->count('client_id');

        $churnClients = Client::where('is_active', true)
            ->where(fn ($q) => $q->where('last_visit_at', '<', now()->subDays(60))->orWhereNull('last_visit_at'))
            ->count();

        $atRiskClients = Client::where('is_active', true)
            ->whereBetween('last_visit_at', [now()->subDays(89), now()->subDays(45)])
            ->with('preferredStylist:id,name')
            ->limit(20)
            ->get(['id', 'first_name', 'last_name', 'phone', 'last_visit_at', 'total_spent', 'visit_count', 'preferred_stylist_id']);

        // Source breakdown for new clients
        $bySource = Client::whereBetween('created_at', [$start, $end])
            ->selectRaw("source, COUNT(*) as count")
            ->groupBy('source')
            ->pluck('count', 'source')
            ->toArray();

        // Top clients by spend
        $topClients = Sale::where('status', 'completed')
            ->whereBetween('completed_at', [$start, $end])
            ->whereNotNull('client_id')
            ->selectRaw('client_id, SUM(total) as total_spent, COUNT(*) as visit_count')
            ->groupBy('client_id')
            ->orderByDesc('total_spent')
            ->limit(10)
            ->with('client:id,first_name,last_name,phone')
            ->get();

        return [
            'new_clients' => $newClients,
            'active_clients' => $clientsWithAppointments,
            'recurring_clients' => max(0, $clientsWithAppointments - $newClients),
            'churn_count' => $churnClients,
            'at_risk_clients' => $atRiskClients->map(fn ($c) => [
                'id' => $c->id,
                'name' => $c->full_name,
                'phone' => $c->phone,
                'last_visit' => $c->last_visit_at?->format('d/m/Y'),
                'days_since' => $c->last_visit_at ? (int) $c->last_visit_at->diffInDays(now()) : null,
                'total_spent' => $c->total_spent,
            ])->toArray(),
            'by_source' => $bySource,
            'top_clients' => $topClients->map(fn ($s) => [
                'name' => $s->client ? $s->client->full_name : '-',
                'phone' => $s->client?->phone,
                'total_spent' => round((float) $s->total_spent, 2),
                'visits' => $s->visit_count,
            ])->toArray(),
        ];
    }

    public function getDemandReport(string $from, string $to): array
    {
        $start = Carbon::parse($from)->startOfDay();
        $end = Carbon::parse($to)->endOfDay();

        $appointments = Appointment::whereBetween('starts_at', [$start, $end])
            ->whereNotIn('status', ['cancelled'])
            ->get(['starts_at']);

        // Build heatmap: day_of_week x hour
        $heatmap = [];
        $days = ['Lun', 'Mar', 'Mie', 'Jue', 'Vie', 'Sab', 'Dom'];
        for ($h = 7; $h <= 21; $h++) {
            $row = ['hour' => sprintf('%02d:00', $h)];
            for ($d = 0; $d < 7; $d++) {
                $row[$days[$d]] = 0;
            }
            $heatmap[] = $row;
        }

        foreach ($appointments as $apt) {
            $dow = $apt->starts_at->dayOfWeekIso - 1; // 0=Mon
            $hour = $apt->starts_at->hour;
            if ($hour >= 7 && $hour <= 21 && $dow >= 0 && $dow < 7) {
                $heatmap[$hour - 7][$days[$dow]]++;
            }
        }

        // Find peak hours
        $maxCount = 0;
        $peakHour = '';
        $peakDay = '';
        foreach ($heatmap as $row) {
            foreach ($days as $d) {
                if ($row[$d] > $maxCount) {
                    $maxCount = $row[$d];
                    $peakHour = $row['hour'];
                    $peakDay = $d;
                }
            }
        }

        return [
            'heatmap' => $heatmap,
            'days' => $days,
            'peak' => ['day' => $peakDay, 'hour' => $peakHour, 'count' => $maxCount],
        ];
    }

    public function getInventoryReport(string $from, string $to): array
    {
        $start = Carbon::parse($from)->startOfDay();
        $end = Carbon::parse($to)->endOfDay();

        $consumed = StockMovement::where('type', 'consumption')
            ->whereBetween('created_at', [$start, $end])
            ->with('product:id,name,unit')
            ->selectRaw('product_id, SUM(ABS(quantity)) as total_consumed')
            ->groupBy('product_id')
            ->orderByDesc('total_consumed')
            ->limit(10)
            ->get();

        $sold = SaleItem::where('type', 'product')
            ->whereHas('sale', fn ($q) => $q->where('status', 'completed')->whereBetween('completed_at', [$start, $end]))
            ->selectRaw('name, SUM(quantity) as total_sold, SUM(subtotal) as total_revenue')
            ->groupBy('name')
            ->orderByDesc('total_sold')
            ->limit(10)
            ->get();

        $noMovement = Product::where('is_active', true)
            ->whereDoesntHave('stockMovements', fn ($q) => $q->whereBetween('created_at', [$start, $end]))
            ->count();

        $totalRevenue = Sale::where('status', 'completed')->whereBetween('completed_at', [$start, $end])->sum('total');
        $materialCost = StockMovement::where('type', 'consumption')
            ->whereBetween('created_at', [$start, $end])
            ->whereNotNull('unit_cost')
            ->selectRaw('SUM(ABS(quantity) * unit_cost) as total_cost')
            ->value('total_cost') ?? 0;
        $costPct = $totalRevenue > 0 ? round((float) $materialCost / (float) $totalRevenue * 100, 1) : 0;

        return [
            'top_consumed' => $consumed->map(fn ($m) => [
                'name' => $m->product?->name ?? '-',
                'unit' => $m->product?->unit ?? '',
                'consumed' => round((float) $m->total_consumed, 1),
            ])->toArray(),
            'top_sold' => $sold->map(fn ($s) => [
                'name' => $s->name,
                'sold' => round((float) $s->total_sold, 1),
                'revenue' => round((float) $s->total_revenue, 2),
            ])->toArray(),
            'no_movement_count' => $noMovement,
            'material_cost_pct' => $costPct,
        ];
    }

    public function getForecast(): array
    {
        $days = [];
        for ($i = 0; $i < 7; $i++) {
            $date = now()->addDays($i);
            $confirmed = Appointment::whereDate('starts_at', $date)
                ->whereIn('status', ['pending', 'confirmed'])
                ->count();
            $estimatedRevenue = Appointment::whereDate('starts_at', $date)
                ->whereIn('status', ['pending', 'confirmed'])
                ->with('service:id,base_price')
                ->get()
                ->sum(fn ($a) => (float) ($a->service?->base_price ?? 0));

            // Historical average for this day of week
            $dow = $date->dayOfWeek;
            $historicalAvg = Appointment::where('status', 'completed')
                ->whereRaw("DAYOFWEEK(starts_at) = ?", [$dow + 1])
                ->where('starts_at', '>=', now()->subDays(90))
                ->count() / 13; // ~13 weeks

            $activeStylists = Stylist::where('is_active', true)->count();
            $maxSlots = $activeStylists * 18; // ~18 slots per stylist per day
            $occupancy = $maxSlots > 0 ? min(100, round($confirmed / $maxSlots * 100)) : 0;

            $days[] = [
                'date' => $date->format('Y-m-d'),
                'label' => $date->format('D d M'),
                'confirmed' => $confirmed,
                'estimated_revenue' => round($estimatedRevenue, 2),
                'historical_avg' => round($historicalAvg, 1),
                'occupancy' => $occupancy,
                'is_today' => $i === 0,
            ];
        }

        return $days;
    }
}
