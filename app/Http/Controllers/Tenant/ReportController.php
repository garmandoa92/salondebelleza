<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Services\ReportService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ReportController extends Controller
{
    public function __construct(
        private ReportService $reportService
    ) {}

    private function getPeriod(Request $request): array
    {
        $period = $request->period ?? 'this_month';
        $now = now();

        return match ($period) {
            'today' => [$now->toDateString(), $now->toDateString()],
            'yesterday' => [$now->subDay()->toDateString(), $now->subDay()->toDateString()],
            'last7' => [$now->copy()->subDays(6)->toDateString(), $now->toDateString()],
            'last30' => [$now->copy()->subDays(29)->toDateString(), $now->toDateString()],
            'this_month' => [$now->copy()->startOfMonth()->toDateString(), $now->toDateString()],
            'last_month' => [$now->copy()->subMonth()->startOfMonth()->toDateString(), $now->copy()->subMonth()->endOfMonth()->toDateString()],
            'custom' => [$request->date_from ?? $now->startOfMonth()->toDateString(), $request->date_to ?? $now->toDateString()],
            default => [$now->copy()->startOfMonth()->toDateString(), $now->toDateString()],
        };
    }

    private function applyBranch(): void
    {
        $this->reportService->setBranch(session('current_branch_id'));
    }

    public function index(Request $request)
    {
        $this->applyBranch();
        [$from, $to] = $this->getPeriod($request);

        return Inertia::render('Reports/Index', [
            'revenue' => $this->reportService->getRevenueReport($from, $to),
            'services' => $this->reportService->getServicesReport($from, $to),
            'stylists' => $this->reportService->getStylistsReport($from, $to),
            'clients' => $this->reportService->getClientsReport($from, $to),
            'demand' => $this->reportService->getDemandReport($from, $to),
            'inventory' => $this->reportService->getInventoryReport($from, $to),
            'forecast' => $this->reportService->getForecast(),
            'period' => ['from' => $from, 'to' => $to, 'selected' => $request->period ?? 'this_month'],
            'stylistsList' => \App\Models\Stylist::where('is_active', true)->get(['id', 'name']),
        ]);
    }

    public function revenue(Request $request)
    {
        $this->applyBranch();
        [$from, $to] = $this->getPeriod($request);
        return response()->json($this->reportService->getRevenueReport($from, $to));
    }

    public function services(Request $request)
    {
        $this->applyBranch();
        [$from, $to] = $this->getPeriod($request);
        return response()->json($this->reportService->getServicesReport($from, $to));
    }

    public function stylists(Request $request)
    {
        $this->applyBranch();
        [$from, $to] = $this->getPeriod($request);
        return response()->json($this->reportService->getStylistsReport($from, $to));
    }

    public function clients(Request $request)
    {
        $this->applyBranch();
        [$from, $to] = $this->getPeriod($request);
        return response()->json($this->reportService->getClientsReport($from, $to));
    }

    public function demand(Request $request)
    {
        $this->applyBranch();
        [$from, $to] = $this->getPeriod($request);
        return response()->json($this->reportService->getDemandReport($from, $to));
    }

    public function inventory(Request $request)
    {
        $this->applyBranch();
        [$from, $to] = $this->getPeriod($request);
        return response()->json($this->reportService->getInventoryReport($from, $to));
    }

    public function forecast()
    {
        $this->applyBranch();
        return response()->json($this->reportService->getForecast());
    }
}
