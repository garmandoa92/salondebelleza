<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\AppointmentWarranty;
use App\Models\User;

class WarrantyService
{
    public function createFromAppointment(Appointment $appointment): ?AppointmentWarranty
    {
        $service = $appointment->service;
        if (!$service || !$service->has_warranty || !$service->warranty_days) {
            return null;
        }

        return AppointmentWarranty::create([
            'appointment_id' => $appointment->id,
            'client_id' => $appointment->client_id,
            'service_id' => $service->id,
            'issued_at' => now(),
            'expires_at' => now()->addDays($service->warranty_days),
            'status' => 'active',
            'notes' => $service->warranty_description,
        ]);
    }

    public function getActiveWarranty(string $clientId, string $serviceId): ?AppointmentWarranty
    {
        return AppointmentWarranty::where('client_id', $clientId)
            ->where('service_id', $serviceId)
            ->where('status', 'active')
            ->where('expires_at', '>', now())
            ->latest('issued_at')
            ->first();
    }

    public function useWarranty(AppointmentWarranty $warranty, Appointment $warrantyAppointment): void
    {
        $warranty->update([
            'status' => 'used',
            'warranty_appointment_id' => $warrantyAppointment->id,
        ]);
    }

    public function expireWarranties(): int
    {
        return AppointmentWarranty::where('status', 'active')
            ->where('expires_at', '<', now())
            ->update(['status' => 'expired']);
    }

    public function void(AppointmentWarranty $warranty, string $reason, User $voidedBy): void
    {
        $warranty->update([
            'status' => 'void',
            'voided_by' => $voidedBy->id,
            'voided_reason' => $reason,
        ]);
    }
}
