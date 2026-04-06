<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\AppointmentDiagnosis;
use Illuminate\Http\Request;

class AppointmentDiagnosisController extends Controller
{
    public function show(Appointment $appointment)
    {
        $diagnosis = AppointmentDiagnosis::where('appointment_id', $appointment->id)->first();

        return response()->json($diagnosis);
    }

    public function store(Request $request, Appointment $appointment)
    {
        $data = $request->validate([
            'initial_condition' => ['nullable', 'string', 'max:100'],
            'skin_condition' => ['nullable', 'string', 'max:100'],
            'products_used' => ['nullable', 'array'],
            'technique' => ['nullable', 'string', 'max:200'],
            'temperature' => ['nullable', 'string', 'max:50'],
            'exposure_time' => ['nullable', 'string', 'max:50'],
            'result' => ['nullable', 'string', 'max:1000'],
            'next_visit_notes' => ['nullable', 'string', 'max:1000'],
            'internal_notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $diagnosis = AppointmentDiagnosis::create([
            ...$data,
            'appointment_id' => $appointment->id,
            'client_id' => $appointment->client_id,
            'created_by' => auth()->id(),
        ]);

        return response()->json($diagnosis);
    }

    public function update(Request $request, Appointment $appointment)
    {
        $data = $request->validate([
            'initial_condition' => ['nullable', 'string', 'max:100'],
            'skin_condition' => ['nullable', 'string', 'max:100'],
            'products_used' => ['nullable', 'array'],
            'technique' => ['nullable', 'string', 'max:200'],
            'temperature' => ['nullable', 'string', 'max:50'],
            'exposure_time' => ['nullable', 'string', 'max:50'],
            'result' => ['nullable', 'string', 'max:1000'],
            'next_visit_notes' => ['nullable', 'string', 'max:1000'],
            'internal_notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $diagnosis = AppointmentDiagnosis::where('appointment_id', $appointment->id)->firstOrFail();
        $diagnosis->update([...$data, 'updated_by' => auth()->id()]);

        return response()->json($diagnosis);
    }
}
