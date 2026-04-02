<?php

namespace App\Services;

use App\Jobs\ProcessSriDocumentJob;
use App\Models\Appointment;
use App\Models\Commission;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SriInvoice;
use App\Services\PackageService;
use App\Services\Sri\SriAccessKeyGenerator;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class SaleService
{
    public function createFromCheckout(array $data, string $userId): Sale
    {
        return DB::transaction(function () use ($data, $userId) {
            $sale = Sale::create([
                'appointment_id' => $data['appointment_id'] ?? null,
                'client_id' => $data['client_id'] ?? null,
                'subtotal' => $data['subtotal'],
                'discount_amount' => $data['discount_amount'] ?? 0,
                'discount_type' => $data['discount_type'] ?? null,
                'discount_reason' => $data['discount_reason'] ?? null,
                'iva_rate' => $data['iva_rate'] ?? tenantIva(),
                'iva_amount' => $data['iva_amount'],
                'total' => $data['total'],
                'tip' => $data['tip'] ?? 0,
                'tip_stylist_id' => $data['tip_stylist_id'] ?? null,
                'payment_methods' => $data['payment_methods'],
                'status' => 'completed',
                'completed_at' => now(),
                'completed_by' => $userId,
            ]);

            // Create sale items
            foreach ($data['items'] as $item) {
                SaleItem::create([
                    'sale_id' => $sale->id,
                    'type' => $item['type'],
                    'reference_id' => $item['reference_id'],
                    'name' => $item['name'],
                    'quantity' => $item['quantity'] ?? 1,
                    'unit_price' => $item['unit_price'],
                    'discount_amount' => $item['discount_amount'] ?? 0,
                    'subtotal' => $item['subtotal'],
                    'iva_rate' => $item['iva_rate'] ?? tenantIva(),
                    'iva_amount' => $item['iva_amount'] ?? round((float) $item['subtotal'] * ($item['iva_rate'] ?? tenantIva()) / 100, 2),
                    'stylist_id' => $item['stylist_id'] ?? null,
                ]);
            }

            // Complete appointment if linked
            if ($data['appointment_id'] ?? null) {
                $appointment = Appointment::find($data['appointment_id']);
                if ($appointment) {
                    $appointment->update(['status' => 'completed']);
                    if ($appointment->client) {
                        $appointment->client->increment('visit_count');
                        $appointment->client->update(['last_visit_at' => now()]);
                    }
                }
            }

            // Calculate commissions
            foreach ($sale->items as $saleItem) {
                if ($saleItem->stylist_id) {
                    $stylist = $saleItem->stylist;
                    $rate = $stylist?->commission_rules['default'] ?? 0;
                    $amount = round((float) $saleItem->subtotal * $rate / 100, 2);

                    if ($amount > 0) {
                        Commission::create([
                            'stylist_id' => $saleItem->stylist_id,
                            'sale_item_id' => $saleItem->id,
                            'amount' => $amount,
                            'rate' => $rate,
                            'status' => 'pending',
                            'period_start' => now()->startOfMonth()->toDateString(),
                            'period_end' => now()->endOfMonth()->toDateString(),
                        ]);
                    }
                }
            }

            // Deduct product stock
            foreach ($data['items'] as $item) {
                if ($item['type'] === 'product') {
                    Product::where('id', $item['reference_id'])
                        ->decrement('stock', $item['quantity'] ?? 1);
                }
            }

            // Update client total_spent
            if ($sale->client_id) {
                $sale->client?->increment('total_spent', (float) $sale->total);
            }

            // Create ClientPackage for package items
            if ($sale->client_id) {
                foreach ($data['items'] as $item) {
                    if (($item['type'] ?? '') === 'package') {
                        (new PackageService())->createClientPackage($sale->client_id, $item['reference_id'], $sale->id);
                    }
                }
            }

            return $sale;
        });
    }

    public function createInvoice(Sale $sale, array $invoiceData, array $tenantConfig): SriInvoice
    {
        return DB::transaction(function () use ($sale, $invoiceData, $tenantConfig) {
            // Get next sequential
            $maxSeq = SriInvoice::where('establishment', $invoiceData['establishment'] ?? '001')
                ->where('emission_point', $invoiceData['emission_point'] ?? '001')
                ->max(DB::raw('CAST(sequential AS UNSIGNED)'));
            $nextSeq = str_pad((string) (($maxSeq ?? 0) + 1), 9, '0', STR_PAD_LEFT);

            $keyGenerator = new SriAccessKeyGenerator();
            $accessKey = $keyGenerator->generate(
                now()->toDateString(),
                $invoiceData['buyer_identification_type'] === 'final_consumer' ? 'sale_note' : 'invoice',
                $tenantConfig['ruc'] ?? '0000000000001',
                $tenantConfig['ambiente_sri'] ?? 'test',
                $invoiceData['establishment'] ?? '001',
                $invoiceData['emission_point'] ?? '001',
                $nextSeq,
            );

            $invoice = SriInvoice::create([
                'invoice_type' => $invoiceData['buyer_identification_type'] === 'final_consumer' ? 'sale_note' : 'invoice',
                'establishment' => $invoiceData['establishment'] ?? '001',
                'emission_point' => $invoiceData['emission_point'] ?? '001',
                'sequential' => $nextSeq,
                'access_key' => $accessKey,
                'issue_date' => now(),
                'environment' => $tenantConfig['ambiente_sri'] ?? 'test',
                'buyer_identification_type' => $invoiceData['buyer_identification_type'] ?? 'final_consumer',
                'buyer_identification' => $invoiceData['buyer_identification'] ?? '9999999999999',
                'buyer_name' => $invoiceData['buyer_name'] ?? 'CONSUMIDOR FINAL',
                'buyer_email' => $invoiceData['buyer_email'] ?? null,
                'subtotal_0' => $sale->items->where('iva_rate', 0)->sum('subtotal'),
                'subtotal_iva' => $sale->items->where('iva_rate', '>', 0)->sum('subtotal'),
                'iva_rate' => tenantIva(),
                'iva_amount' => $sale->iva_amount,
                'total' => $sale->total,
                'sri_status' => 'draft',
            ]);

            $sale->update(['sri_invoice_id' => $invoice->id]);

            // Dispatch job to process with SRI
            $saleItems = $sale->items->map(fn ($i) => $i->toArray())->toArray();
            ProcessSriDocumentJob::dispatch(
                $invoice->id,
                $tenantConfig,
                $saleItems,
                $sale->payment_methods ?? [['method' => 'cash', 'amount' => (float) $sale->total]],
            );

            return $invoice;
        });
    }

    public function getDaySummary(): array
    {
        $today = Carbon::today();
        $sales = Sale::where('status', 'completed')
            ->whereDate('completed_at', $today)
            ->get();

        return [
            'total' => $sales->sum('total'),
            'count' => $sales->count(),
            'cash' => $sales->sum(function ($s) {
                return collect($s->payment_methods ?? [])->where('method', 'cash')->sum('amount');
            }),
            'card' => $sales->sum(function ($s) {
                return collect($s->payment_methods ?? [])->whereIn('method', ['card_debit', 'card_credit'])->sum('amount');
            }),
            'transfer' => $sales->sum(function ($s) {
                return collect($s->payment_methods ?? [])->where('method', 'transfer')->sum('amount');
            }),
        ];
    }
}
