<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Services\SessionNoteService;
use Illuminate\Http\Request;

class SessionNoteController extends Controller
{
    public function __construct(private SessionNoteService $service) {}

    public function show(Appointment $appointment)
    {
        $appointment->load('client.healthProfile', 'sessionNote');
        $data = $this->service->getForAppointment($appointment);

        return response()->json($data);
    }

    public function save(Request $request, Appointment $appointment)
    {
        $validated = $request->validate([
            'body_map' => 'nullable|array',
            'body_map.*.zone_id' => 'required|string|max:50',
            'body_map.*.label' => 'required|string|max:100',
            'body_map.*.state' => 'required|in:worked,tension,avoided',
            'body_map.*.view' => 'required|in:front,back',
            'techniques' => 'nullable|array',
            'techniques.*' => 'string|max:100',
            'products_used' => 'nullable|array',
            'products_used.*' => 'string|max:200',
            'actual_duration_minutes' => 'nullable|integer|min:1|max:480',
            'tension_level' => 'nullable|in:low,medium,high',
            'observations' => 'nullable|string|max:2000',
            'next_session_recommendation' => 'nullable|string|max:1000',
            'client_recommendation' => 'nullable|string|max:500',
            'send_whatsapp' => 'boolean',
        ]);

        $appointment->load('client');
        $note = $this->service->save($appointment, $validated);

        return response()->json($note);
    }
}
