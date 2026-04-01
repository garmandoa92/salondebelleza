<?php

namespace App\Services;

use App\Models\ClientPackage;
use App\Models\ClientPackageItem;
use App\Models\Package;

class PackageService
{
    public function createClientPackage(string $clientId, string $packageId, ?string $saleId = null): ClientPackage
    {
        $package = Package::findOrFail($packageId);

        $cp = ClientPackage::create([
            'client_id' => $clientId,
            'package_id' => $packageId,
            'sale_id' => $saleId,
            'package_name' => $package->name,
            'package_price' => $package->price,
            'purchased_at' => now(),
            'expires_at' => now()->addDays($package->validity_days),
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

    public function useSession(ClientPackageItem $item, string $appointmentId): void
    {
        $item->increment('used_quantity');
        $item->update([
            'last_used_at' => now(),
            'last_appointment_id' => $appointmentId,
        ]);

        // Check if entire package is completed
        $cp = $item->clientPackage->load('items');
        if ($cp->isFullyUsed()) {
            $cp->update(['status' => 'completed']);
        }
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
            ->with('clientPackage:id,package_name,expires_at')
            ->first();
    }
}
