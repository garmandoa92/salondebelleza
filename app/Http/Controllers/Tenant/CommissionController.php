<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Stylist;
use App\Services\CommissionService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;

class CommissionController extends Controller
{
    public function __construct(
        private CommissionService $commissionService
    ) {}

    public function index(Request $request)
    {
        $periodStart = $request->period_start ?? now()->startOfMonth()->toDateString();
        $periodEnd = $request->period_end ?? now()->endOfMonth()->toDateString();

        $summary = $this->commissionService->getPeriodSummary($periodStart, $periodEnd);

        $totalCommissions = collect($summary)->sum('commission_amount');
        $totalSold = collect($summary)->sum('total_sold');

        return Inertia::render('Commissions/Index', [
            'summary' => $summary,
            'period' => ['start' => $periodStart, 'end' => $periodEnd],
            'totals' => ['commissions' => $totalCommissions, 'sold' => $totalSold],
        ]);
    }

    public function stylist(Request $request, Stylist $stylist)
    {
        $periodStart = $request->period_start ?? now()->startOfMonth()->toDateString();
        $periodEnd = $request->period_end ?? now()->endOfMonth()->toDateString();

        $detail = $this->commissionService->getStylistDetail($stylist->id, $periodStart, $periodEnd);
        $totalAmount = collect($detail)->sum('amount');

        return Inertia::render('Commissions/Stylist', [
            'stylist' => $stylist,
            'detail' => $detail,
            'period' => ['start' => $periodStart, 'end' => $periodEnd],
            'total' => $totalAmount,
        ]);
    }

    public function pay(Request $request)
    {
        $request->validate([
            'commission_ids' => ['required', 'array'],
            'commission_ids.*' => ['uuid'],
        ]);

        $count = $this->commissionService->payCommissions($request->commission_ids, auth()->id());

        return back()->with('success', "{$count} comisiones marcadas como pagadas.");
    }
}
