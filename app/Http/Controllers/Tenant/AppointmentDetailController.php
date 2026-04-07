<?php

namespace App\Http\Controllers\Tenant;

use App\Constants\HealthProfileConstants;
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
            'healthConfirmations',
            'stylist:id,name,color',
            'service:id,name,base_price,duration_minutes',
            'branch:id,name',
        ]);

        // Recent history: last 3 completed appointments of same client
        $recentHistory = Appointment::where('client_id', $appointment->client_id)
            ->where('id', '!=', $appointment->id)
            ->where('status', 'completed')
            ->with(['sessionNote', 'service:id,name'])
            ->orderBy('starts_at', 'desc')
            ->limit(3)
            ->get();

        $healthProfile = $this->healthService->getOrCreate($appointment->client_id);
        $noteData = $this->noteService->getForAppointment($appointment);
        $isConfirmed = $this->healthService->isConfirmed($appointment);
        $confirmations = $this->healthService->getConfirmations($appointment);

        return Inertia::render('Appointments/Detail', [
            'appointment' => $appointment,
            'healthProfile' => $healthProfile,
            'alertSummary' => $healthProfile->getAlertSummary(),
            'hasAlerts' => $healthProfile->hasCriticalAlerts(),
            'isConfirmed' => $isConfirmed,
            'confirmations' => $confirmations,
            'sessionNote' => $noteData,
            'recentHistory' => $recentHistory,
            'constants' => [
                'techniques' => HealthProfileConstants::TECHNIQUES,
            ],
        ]);
    }
}
