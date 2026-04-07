<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\Client;
use App\Services\ClientHealthProfileService;
use Illuminate\Http\Request;

class ClientHealthProfileController extends Controller
{
    public function __construct(private ClientHealthProfileService $service) {}

    public function show(Client $client)
    {
        $profile = $this->service->getOrCreate($client->id);

        return response()->json([
            'profile' => $profile,
            'alert_summary' => $profile->getAlertSummary(),
            'is_outdated' => $profile->isOutdated(),
            'has_alerts' => $profile->hasCriticalAlerts(),
        ]);
    }

    public function update(Request $request, Client $client)
    {
        $validated = $request->validate([
            'allergies' => 'nullable|array',
            'allergies.*' => 'string|max:100',
            'allergies_notes' => 'nullable|string|max:500',
            'medical_conditions' => 'nullable|array',
            'medical_conditions.*' => 'string|max:100',
            'medical_notes' => 'nullable|string|max:500',
            'current_medications' => 'nullable|string|max:500',
            'contraindications' => 'nullable|string|max:1000',
            'avoid_zones' => 'nullable|array',
            'avoid_zones.*.zone_id' => 'required|string|max:50',
            'avoid_zones.*.label' => 'required|string|max:100',
            'avoid_zones.*.note' => 'nullable|string|max:500',
            'avoid_zones.*.view' => 'required|in:front,back',
            'pressure_preference' => 'nullable|integer|min:1|max:5',
            'personal_preferences' => 'nullable|array',
            'personal_preferences.*' => 'string|max:100',
            'therapist_notes' => 'nullable|string|max:1000',
        ]);

        $profile = $this->service->getOrCreate($client->id);
        $updated = $this->service->update($profile, $validated);

        return response()->json($updated);
    }

    public function appointmentAlert(Appointment $appointment)
    {
        $appointment->load('client', 'service:id,name');
        $profile = $this->service->getOrCreate($appointment->client_id);

        return response()->json([
            'client' => [
                'id' => $appointment->client->id,
                'name' => $appointment->client->first_name . ' ' . $appointment->client->last_name,
                'phone' => $appointment->client->phone,
            ],
            'alert_summary' => $profile->getAlertSummary(),
            'has_alerts' => $profile->hasCriticalAlerts(),
            'is_outdated' => $profile->isOutdated(),
            'is_confirmed' => $this->service->isConfirmed($appointment),
            'confirmations' => $this->service->getConfirmations($appointment),
        ]);
    }

    public function confirmReading(Appointment $appointment)
    {
        $confirmation = $this->service->confirmReading($appointment);

        return response()->json([
            'confirmed' => true,
            'confirmed_by' => $confirmation->user_name,
            'confirmed_at' => $confirmation->confirmed_at->format('d/m/Y H:i'),
        ]);
    }
}
