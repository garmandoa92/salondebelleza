<?php

namespace App\Services;

use App\Models\ClientPackage;
use App\Models\ClientPackageItem;
use App\Models\Package;
use App\Models\PackageUsageLog;
use Illuminate\Support\Facades\DB;

class PackageService
{
    public function generateReceiptNumber(): string
    {
        return DB::transaction(function () {
            $prefix = 'PKG-' . now()->format('Ym') . '-';
            $last = ClientPackage::where('receipt_number', 'like', $prefix . '%')
                ->lockForUpdate()
                ->orderByDesc('receipt_number')
                ->value('receipt_number');

            $seq = $last ? (int) substr($last, -4) + 1 : 1;

            return $prefix . str_pad($seq, 4, '0', STR_PAD_LEFT);
        });
    }

    public function createClientPackage(string $clientId, string $packageId, ?string $saleId = null): ClientPackage
    {
        $package = Package::findOrFail($packageId);

        $cp = ClientPackage::create([
            'receipt_number' => $this->generateReceiptNumber(),
            'client_id' => $clientId,
            'package_id' => $packageId,
            'sale_id' => $saleId,
            'package_name' => $package->name,
            'package_price' => $package->price,
            'purchased_at' => now(),
            'expires_at' => $package->validity_days ? now()->addDays($package->validity_days) : null,
            'status' => 'active',
        ]);

        foreach ($package->items as $item) {
            ClientPackageItem::create([
                'client_package_id' => $cp->id,
                'service_id' => $item['service_id'],
                'service_name' => $item['service_name'],
                'total_quantity' => $item['quantity'],
                'used_quantity' => 0,
            ]);
        }

        return $cp->load('items');
    }

    public function useSessions(ClientPackageItem $item, int $sessions, string $appointmentId, string $usedBy): PackageUsageLog
    {
        $before = $item->used_quantity;
        $after = $before + $sessions;

        $item->update([
            'used_quantity' => $after,
            'last_used_at' => now(),
            'last_appointment_id' => $appointmentId,
        ]);

        $log = PackageUsageLog::create([
            'client_package_id' => $item->client_package_id,
            'client_package_item_id' => $item->id,
            'appointment_id' => $appointmentId,
            'service_id' => $item->service_id,
            'sessions_used' => $sessions,
            'sessions_before' => $before,
            'sessions_after' => $after,
            'used_by' => $usedBy,
            'created_at' => now(),
        ]);

        $this->checkCompletion($item->clientPackage->load('items'));

        return $log;
    }

    public function checkCompletion(ClientPackage $cp): bool
    {
        if ($cp->isFullyUsed()) {
            $cp->update(['status' => 'completed']);
            return true;
        }
        return false;
    }

    public function getActivePackageForClient(string $clientId, string $serviceId): ?ClientPackageItem
    {
        return ClientPackageItem::where('service_id', $serviceId)
            ->whereRaw('used_quantity < total_quantity')
            ->whereHas('clientPackage', fn ($q) => $q
                ->where('client_id', $clientId)
                ->where('status', 'active')
                ->where(fn ($q2) => $q2->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            )
            ->with('clientPackage:id,package_name,receipt_number,expires_at')
            ->first();
    }
}
