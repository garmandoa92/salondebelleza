<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\BlockedTime;
use App\Models\Service;
use App\Models\Stylist;
use Carbon\Carbon;

class AvailabilityService
{
    public function getAvailableSlots(string $stylistId, string $serviceId, string $date): array
    {
        $service = Service::findOrFail($serviceId);
        $blockMinutes = $service->duration_minutes + $service->preparation_minutes;

        $stylist = Stylist::findOrFail($stylistId);
        $dayOfWeek = strtolower(Carbon::parse($date)->format('l'));
        $schedule = $stylist->schedule[$dayOfWeek] ?? [];
        if (empty($schedule)) return [];

        $existing = Appointment::where('stylist_id', $stylistId)
            ->whereDate('starts_at', $date)
            ->whereNotIn('status', ['cancelled'])
            ->get(['starts_at', 'ends_at']);

        $blocks = BlockedTime::where(function ($q) use ($stylistId, $date) {
            $q->where('stylist_id', $stylistId)
                ->orWhereNull('stylist_id');
        })
            ->whereDate('starts_at', '<=', $date)
            ->whereDate('ends_at', '>=', $date)
            ->get(['starts_at', 'ends_at']);

        $slots = [];
        foreach ($schedule as $shift) {
            $current = Carbon::parse($date . ' ' . $shift['start']);
            $shiftEnd = Carbon::parse($date . ' ' . $shift['end']);

            while ($current->copy()->addMinutes($blockMinutes)->lte($shiftEnd)) {
                $slotEnd = $current->copy()->addMinutes($blockMinutes);

                $hasConflict = $existing->some(fn ($apt) =>
                    $current->lt($apt->ends_at) && $slotEnd->gt($apt->starts_at)
                );

                if (! $hasConflict) {
                    $hasConflict = $blocks->some(fn ($b) =>
                        $current->lt($b->ends_at) && $slotEnd->gt($b->starts_at)
                    );
                }

                if (! $hasConflict && $date === now()->toDateString()) {
                    $hasConflict = $current->lt(now()->addMinutes(30));
                }

                if (! $hasConflict) {
                    $slots[] = [
                        'time' => $current->format('H:i'),
                        'datetime' => $current->toIso8601String(),
                        'available' => true,
                    ];
                }

                $current->addMinutes(15);
            }
        }

        return $slots;
    }
}
