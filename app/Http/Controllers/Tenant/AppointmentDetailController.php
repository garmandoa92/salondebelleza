<?php

namespace App\Http\Controllers\Tenant;

use App\Constants\HealthProfileConstants;
use App\Constants\ServiceTypeFields;
use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Services\ClientHealthProfileService;
use App\Services\SessionNoteService;
use Inertia\Inertia;

class AppointmentDetailController extends Controller
{
    public function __construct(
        private ClientHealthProfileService $healthService,
        private SessionNoteService $noteService,
    ) {}

    public function show(Appointment $appointment)
    {
        $appointment->load([
            'client.healthProfile',
            'sessionNote',
            'diagnosis',
            'healthConfirmations',
            'stylist:id,name,color',
            'service',
        ]);

        $recentHistory = Appointment::where('client_id', $appointment->client_id)
            ->where('id', '!=', $appointment->id)
            ->where('status', 'completed')
            ->with(['sessionNote', 'diagnosis', 'service:id,name,service_type'])
            ->orderBy('starts_at', 'desc')
            ->limit(3)
            ->get();

        $healthProfile = $this->healthService->getOrCreate($appointment->client_id);
        $noteData = $this->noteService->getForAppointment($appointment);
        $isConfirmed = $this->healthService->isConfirmed($appointment);
        $confirmations = $this->healthService->getConfirmations($appointment);

        // Unified note system: detect service type and load correct data
        $serviceType = $appointment->service?->service_type ?? 'other';
        $fields = ServiceTypeFields::forType($serviceType);
        $techniques = HealthProfileConstants::TECHNIQUES_BY_TYPE[$serviceType]
            ?? HealthProfileConstants::TECHNIQUES_BY_TYPE['other'];

        // Load unified note based on service type
        $unifiedNote = $fields['body_map']
            ? $appointment->sessionNote
            : $appointment->diagnosis;

        return Inertia::render('Appointments/Detail', [
            'appointment' => $appointment,
            'healthProfile' => $healthProfile,
            'alertSummary' => $healthProfile->getAlertSummary(),
            'hasAlerts' => $healthProfile->hasCriticalAlerts(),
            'isConfirmed' => $isConfirmed,
            'confirmations' => $confirmations,
            'sessionNote' => $noteData,
            'recentHistory' => $recentHistory,
            'serviceType' => $serviceType,
            'fields' => $fields,
            'techniques' => $techniques,
            'unifiedNote' => $unifiedNote,
        ]);
    }
}
