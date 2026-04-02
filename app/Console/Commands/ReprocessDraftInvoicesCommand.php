<?php

namespace App\Console\Commands;

use App\Jobs\ProcessSriDocumentJob;
use App\Models\SriInvoice;
use App\Models\Tenant;
use Illuminate\Console\Command;

class ReprocessDraftInvoicesCommand extends Command
{
    protected $signature = 'sri:reprocess-drafts';
    protected $description = 'Reprocess SRI invoices stuck in draft status';

    public function handle(): int
    {
        $tenants = Tenant::all();

        foreach ($tenants as $tenant) {
            $tenant->run(function () use ($tenant) {
                $drafts = SriInvoice::where('sri_status', 'draft')->with('sale.items')->get();

                if ($drafts->isEmpty()) {
                    $this->info("Tenant {$tenant->id}: no drafts");
                    return;
                }

                $this->info("Tenant {$tenant->id}: {$drafts->count()} draft invoices");

                $settings = $tenant->settings ?? [];
                $tenantConfig = [
                    'ruc' => $tenant->ruc ?? $settings['ruc'] ?? '0000000000001',
                    'razon_social' => $tenant->razon_social ?? $settings['razon_social'] ?? $tenant->name,
                    'nombre_comercial' => $tenant->name,
                    'ambiente_sri' => $settings['ambiente_sri'] ?? 'test',
                    'establecimiento' => $settings['establecimiento'] ?? '001',
                    'punto_emision' => $settings['punto_emision'] ?? '001',
                    'obligado_contabilidad' => $settings['obligado_contabilidad'] ?? 'NO',
                    'direccion' => $tenant->address ?? 'Ecuador',
                ];

                foreach ($drafts as $invoice) {
                    $saleItems = $invoice->sale?->items?->map(fn ($i) => $i->toArray())->toArray() ?? [];
                    $payments = $invoice->sale?->payment_methods ?? [['method' => 'cash', 'amount' => (float) $invoice->total]];

                    $this->line("  Processing {$invoice->sequential}...");

                    try {
                        ProcessSriDocumentJob::dispatchSync(
                            $invoice->id,
                            $tenantConfig,
                            $saleItems,
                            $payments,
                        );

                        $invoice->refresh();
                        $status = $invoice->sri_status instanceof \BackedEnum ? $invoice->sri_status->value : $invoice->sri_status;
                        $this->info("  → {$status}" . ($invoice->error_message ? " ({$invoice->error_message})" : ''));
                    } catch (\Throwable $e) {
                        $this->error("  → Error: {$e->getMessage()}");
                    }
                }
            });
        }

        return self::SUCCESS;
    }
}
