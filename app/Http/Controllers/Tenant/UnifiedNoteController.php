<?php

namespace App\Http\Controllers\Tenant;

use App\Constants\ServiceTypeFields;
use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\AppointmentDiagnosis;
use App\Models\AppointmentSessionNote;
use App\Services\WhatsAppService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class UnifiedNoteController extends Controller
{
    public function __construct(private WhatsAppService $whatsapp) {}

    public function save(Request $request, Appointment $appointment)
    {
        $serviceType = $appointment->service?->service_type ?? 'other';
        $fields = ServiceTypeFields::forType($serviceType);

        $validated = $request->validate([
            'initial_condition' => 'nullable|string|max:1000',
            'technique' => 'nullable|string|max:500',
            'techniques_used' => 'nullable|array',
            'techniques_used.*' => 'string|max:100',
            'temperature' => 'nullable|string|max:100',
            'exposure_time' => 'nullable|string|max:100',
            'products_used' => 'nullable|array',
            'products_used.*' => 'string|max:200',
            'result' => 'nullable|string|max:1000',
            'next_visit_notes' => 'nullable|string|max:1000',
            'internal_notes' => 'nullable|string|max:1000',
            'body_map' => 'nullable|array',
            'actual_duration_minutes' => 'nullable|integer',
            'tension_level' => 'nullable|in:low,medium,high',
            'client_recommendation' => 'nullable|string|max:500',
            'send_whatsapp' => 'boolean',
        ]);

        if ($fields['body_map']) {
            // Spa/facial → guardar en appointment_session_notes
            $note = AppointmentSessionNote::updateOrCreate(
                ['appointment_id' => $appointment->id],
                [
                    'user_id' => Auth::id(),
                    'body_map' => $validated['body_map'] ?? [],
                    'techniques' => $validated['techniques_used'] ?? [],
                    'products_used' => $validated['products_used'] ?? [],
                    'actual_duration_minutes' => $validated['actual_duration_minutes'],
                    'tension_level' => $validated['tension_level'],
                    'observations' => $validated['initial_condition'],
                    'next_session_recommendation' => $validated['next_visit_notes'],
                    'client_recommendation' => $validated['client_recommendation'],
                ]
            );

            if (!empty($validated['send_whatsapp']) && !empty($validated['client_recommendation'])) {
                $appointment->load('client');
                if ($appointment->client?->phone && $this->whatsapp->isConfigured()) {
                    $this->whatsapp->sendText($appointment->client->phone, $validated['client_recommendation']);
                    $note->update(['whatsapp_sent' => true, 'whatsapp_sent_at' => Carbon::now()]);
                }
            }
        } else {
            // Hair/nails/brows/other → guardar en appointment_diagnoses
            AppointmentDiagnosis::updateOrCreate(
                ['appointment_id' => $appointment->id],
                [
                    'initial_condition' => $validated['initial_condition'],
                    'technique' => implode(', ', $validated['techniques_used'] ?? []) ?: $validated['technique'],
                    'temperature' => $validated['temperature'],
                    'exposure_time' => $validated['exposure_time'],
                    'products_used' => $validated['products_used'] ?? [],
                    'result' => $validated['result'],
                    'next_visit_notes' => $validated['next_visit_notes'],
                    'internal_notes' => $validated['internal_notes'],
                ]
            );
        }

        return response()->json(['saved' => true]);
    }
}
