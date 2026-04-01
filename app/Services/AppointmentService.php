<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\Client;
use App\Models\Service;
use App\Models\Stylist;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class AppointmentService
{
    public function getEventsForCalendar(string $start, string $end, ?array $stylistIds = null, ?string $branchId = null): array
    {
        $query = Appointment::with(['client:id,first_name,last_name,phone,allergies', 'stylist:id,name,color', 'service:id,name,duration_minutes,base_price'])
            ->whereBetween('starts_at', [$start, $end]);

        if ($stylistIds) {
            $query->whereIn('stylist_id', $stylistIds);
        }

        if ($branchId) {
            $query->where('branch_id', $branchId);
        }

        $statusColors = [
            'pending' => '#94a3b8',
            'confirmed' => '#3b82f6',
            'in_progress' => '#22c55e',
            'completed' => '#15803d',
            'cancelled' => '#ef4444',
            'no_show' => '#f97316',
        ];

        return $query->get()->map(function (Appointment $apt) use ($statusColors) {
            $status = $apt->status->value;
            return [
                'id' => $apt->id,
                'resourceId' => $apt->stylist_id,
                'title' => $apt->client?->full_name ?? 'Sin cliente',
                'start' => $apt->starts_at->toIso8601String(),
                'end' => $apt->ends_at->toIso8601String(),
                'backgroundColor' => $apt->stylist?->color ?? '#3b82f6',
                'borderColor' => $statusColors[$status] ?? '#94a3b8',
                'textColor' => '#ffffff',
                'editable' => ! in_array($status, ['completed', 'cancelled']),
                'extendedProps' => [
                    'client_name' => $apt->client?->full_name ?? 'Sin cliente',
                    'client_phone' => $apt->client?->phone ?? '',
                    'service_name' => $apt->service?->name ?? '',
                    'service_duration' => $apt->service?->duration_minutes ?? 0,
                    'price' => number_format((float) ($apt->service?->base_price ?? 0), 2),
                    'stylist_name' => $apt->stylist?->name ?? '',
                    'stylist_color' => $apt->stylist?->color ?? '#3b82f6',
                    'status' => $status,
                    'notes' => $apt->notes,
                    'internal_notes' => $apt->internal_notes,
                    'started_at' => $status === 'in_progress' ? $apt->updated_at?->toIso8601String() : null,
                    'allergies' => $apt->client?->allergies,
                ],
            ];
        })->toArray();
    }

    public function getWeekOccupancy(string $weekStart): array
    {
        $start = Carbon::parse($weekStart)->startOfWeek();
        $stylists = Stylist::where('is_active', true)->get();
        $totalSlotsPerDay = $stylists->count() * 36; // ~9 hours * 4 slots per hour

        $result = [];
        $dayNames = ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'];

        for ($i = 0; $i < 7; $i++) {
            $date = $start->copy()->addDays($i);
            $count = Appointment::whereDate('starts_at', $date)
                ->whereNotIn('status', ['cancelled'])
                ->count();
            $pct = $totalSlotsPerDay > 0 ? min(100, round($count / $totalSlotsPerDay * 100)) : 0;
            $result[$dayNames[$i]] = $pct;
        }

        return $result;
    }

    public function store(array $data): Appointment
    {
        $service = Service::findOrFail($data['service_id']);
        $startsAt = Carbon::parse($data['starts_at']);

        if (empty($data['ends_at'])) {
            $data['ends_at'] = $startsAt->copy()->addMinutes($service->duration_minutes)->toIso8601String();
        }

        return Appointment::create($data);
    }

    public function update(Appointment $appointment, array $data): Appointment
    {
        $appointment->update($data);
        return $appointment;
    }

    public function cancel(Appointment $appointment, string $reason, string $cancelledBy): Appointment
    {
        $appointment->update([
            'status' => 'cancelled',
            'cancellation_reason' => $reason,
            'cancelled_by' => $cancelledBy,
            'cancelled_at' => now(),
        ]);
        return $appointment;
    }

    public function confirm(Appointment $appointment): Appointment
    {
        $appointment->update([
            'status' => 'confirmed',
            'confirmed_at' => now(),
        ]);
        return $appointment;
    }

    public function start(Appointment $appointment): Appointment
    {
        $appointment->update(['status' => 'in_progress']);
        return $appointment;
    }

    public function complete(Appointment $appointment): Appointment
    {
        $appointment->update(['status' => 'completed']);

        // Update client stats
        if ($appointment->client) {
            $client = $appointment->client;
            $client->increment('visit_count');
            $client->update(['last_visit_at' => now()]);
        }

        return $appointment;
    }

    public function markNoShow(Appointment $appointment): Appointment
    {
        $appointment->update(['status' => 'no_show']);
        return $appointment;
    }
}
