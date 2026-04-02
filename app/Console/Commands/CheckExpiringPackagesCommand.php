<?php

namespace App\Console\Commands;

use App\Models\ClientPackage;
use App\Models\Tenant;
use Illuminate\Console\Command;

class CheckExpiringPackagesCommand extends Command
{
    protected $signature = 'packages:check-expiring';
    protected $description = 'Check for expiring client packages and send notifications';

    public function handle(): int
    {
        $tenants = Tenant::all();

        foreach ($tenants as $tenant) {
            $tenant->run(function () use ($tenant) {
                $this->checkTenant($tenant);
            });
        }

        return self::SUCCESS;
    }

    private function checkTenant(Tenant $tenant): void
    {
        // Expire packages past their date
        $expired = ClientPackage::where('status', 'active')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', now())
            ->get();

        foreach ($expired as $cp) {
            $cp->update(['status' => 'expired']);
            $remaining = $cp->items->sum(fn ($i) => $i->total_quantity - $i->used_quantity);
            $this->info("  Expired: {$cp->package_name} ({$cp->receipt_number}) — {$remaining} sessions lost");
        }

        // Warn: expiring in 15 days
        $in15 = ClientPackage::where('status', 'active')
            ->whereDate('expires_at', now()->addDays(15)->toDateString())
            ->with('client:id,first_name,phone')
            ->get();

        foreach ($in15 as $cp) {
            $remaining = $cp->items->sum(fn ($i) => $i->total_quantity - $i->used_quantity);
            $this->info("  15-day warning: {$cp->client?->first_name} — {$cp->package_name} ({$cp->receipt_number}) — {$remaining} sessions left");
            // WhatsApp would be dispatched here via SendWhatsAppJob
        }

        // Warn: expiring in 7 days
        $in7 = ClientPackage::where('status', 'active')
            ->whereDate('expires_at', now()->addDays(7)->toDateString())
            ->with('client:id,first_name,phone')
            ->get();

        foreach ($in7 as $cp) {
            $remaining = $cp->items->sum(fn ($i) => $i->total_quantity - $i->used_quantity);
            $this->warn("  7-day warning: {$cp->client?->first_name} — {$cp->package_name} ({$cp->receipt_number}) — {$remaining} sessions left");
        }

        // Warn: expiring tomorrow
        $in1 = ClientPackage::where('status', 'active')
            ->whereDate('expires_at', now()->addDay()->toDateString())
            ->with('client:id,first_name,phone')
            ->get();

        foreach ($in1 as $cp) {
            $remaining = $cp->items->sum(fn ($i) => $i->total_quantity - $i->used_quantity);
            $this->error("  TOMORROW: {$cp->client?->first_name} — {$cp->package_name} ({$cp->receipt_number}) — {$remaining} sessions left");
        }

        $total = $expired->count() + $in15->count() + $in7->count() + $in1->count();
        if ($total > 0) {
            $this->info("Tenant {$tenant->id}: {$total} package alerts");
        }
    }
}
