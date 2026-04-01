<?php

namespace App\Services;

use App\Models\Commission;
use App\Models\Sale;
use App\Models\Stylist;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class CommissionService
{
    public function calculateForSale(Sale $sale): void
    {
        $sale->load('items');

        foreach ($sale->items as $item) {
            if (! $item->stylist_id) continue;

            $stylist = Stylist::find($item->stylist_id);
            if (! $stylist) continue;

            $rate = $this->getRate($stylist, $item->reference_id);
            $amount = round((float) $item->subtotal * $rate / 100, 2);

            if ($amount <= 0) continue;

            Commission::create([
                'stylist_id' => $item->stylist_id,
                'sale_item_id' => $item->id,
                'amount' => $amount,
                'rate' => $rate,
                'status' => 'pending',
                'period_start' => now()->startOfMonth()->toDateString(),
                'period_end' => now()->endOfMonth()->toDateString(),
            ]);
        }
    }

    private function getRate(Stylist $stylist, string $referenceId): float
    {
        $rules = $stylist->commission_rules ?? [];

        // Try category-specific rate
        if (! empty($rules['by_category'])) {
            $service = \App\Models\Service::find($referenceId);
            if ($service && isset($rules['by_category'][$service->service_category_id])) {
                return (float) $rules['by_category'][$service->service_category_id];
            }
        }

        return (float) ($rules['default'] ?? 0);
    }

    public function getPeriodSummary(string $periodStart, string $periodEnd): array
    {
        $stylists = Stylist::where('is_active', true)->get();

        return $stylists->map(function (Stylist $stylist) use ($periodStart, $periodEnd) {
            $commissions = Commission::where('stylist_id', $stylist->id)
                ->whereBetween('period_start', [$periodStart, $periodEnd])
                ->get();

            $totalAmount = $commissions->sum('amount');
            $avgRate = $commissions->avg('rate') ?? 0;
            $allPaid = $commissions->isNotEmpty() && $commissions->every(fn ($c) => $c->status->value === 'paid');

            return [
                'stylist_id' => $stylist->id,
                'stylist_name' => $stylist->name,
                'stylist_color' => $stylist->color,
                'services_count' => $commissions->count(),
                'total_sold' => $commissions->sum(fn ($c) => (float) $c->saleItem?->subtotal ?? 0),
                'avg_rate' => round($avgRate, 1),
                'commission_amount' => $totalAmount,
                'status' => $allPaid ? 'paid' : ($commissions->isEmpty() ? 'empty' : 'pending'),
            ];
        })->filter(fn ($s) => $s['services_count'] > 0)->values()->toArray();
    }

    public function getStylistDetail(string $stylistId, string $periodStart, string $periodEnd): array
    {
        $commissions = Commission::where('stylist_id', $stylistId)
            ->whereBetween('period_start', [$periodStart, $periodEnd])
            ->with(['saleItem.sale.client:id,first_name,last_name', 'saleItem'])
            ->orderBy('created_at')
            ->get();

        return $commissions->map(function ($c) {
            return [
                'id' => $c->id,
                'date' => $c->created_at->format('d/m/Y'),
                'client' => $c->saleItem?->sale?->client ? $c->saleItem->sale->client->full_name : '-',
                'service' => $c->saleItem?->name ?? '-',
                'price' => $c->saleItem?->subtotal ?? 0,
                'rate' => $c->rate,
                'amount' => $c->amount,
                'status' => $c->status->value,
            ];
        })->toArray();
    }

    public function payCommissions(array $commissionIds, string $userId): int
    {
        return Commission::whereIn('id', $commissionIds)
            ->where('status', 'pending')
            ->update([
                'status' => 'paid',
                'paid_at' => now(),
                'paid_by' => $userId,
            ]);
    }
}
