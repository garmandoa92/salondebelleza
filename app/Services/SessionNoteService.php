<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\AppointmentSessionNote;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;

class SessionNoteService
{
    public function __construct(private WhatsAppService $whatsapp) {}

    public function save(Appointment $appointment, array $data): AppointmentSessionNote
    {
        $note = AppointmentSessionNote::updateOrCreate(
            ['appointment_id' => $appointment->id],
            [
                ...$data,
                'user_id' => Auth::id(),
            ]
        );

        if (!empty($data['send_whatsapp']) && !empty($data['client_recommendation'])) {
            $this->sendClientRecommendation($appointment, $note);
        }

        return $note->fresh();
    }

    public function getForAppointment(Appointment $appointment): array
    {
        $appointment->load('sessionNote', 'client.healthProfile');
        $note = $appointment->sessionNote;
        $profile = $appointment->client?->healthProfile;

        $inheritedAvoidZones = collect($profile?->avoid_zones ?? [])
            ->map(fn ($z) => [
                'zone_id' => $z['zone_id'],
                'label' => $z['label'],
                'state' => 'avoided',
                'view' => $z['view'],
                'inherited' => true,
                'note' => $z['note'] ?? null,
            ])
            ->toArray();

        return [
            'note' => $note,
            'body_map' => $note?->body_map ?? [],
            'inherited_avoid_zones' => $inheritedAvoidZones,
            'techniques' => $note?->techniques ?? [],
            'products_used' => $note?->products_used ?? [],
        ];
    }

    private function sendClientRecommendation(Appointment $appointment, AppointmentSessionNote $note): void
    {
        $client = $appointment->client;
        if (!$client?->phone || !$note->client_recommendation) {
            return;
        }

        if (!$this->whatsapp->isConfigured()) {
            return;
        }

        $this->whatsapp->sendText($client->phone, $note->client_recommendation);

        $note->update([
            'whatsapp_sent' => true,
            'whatsapp_sent_at' => Carbon::now(),
        ]);
    }
}
