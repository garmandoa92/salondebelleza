<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\AppointmentPhoto;
use App\Services\AppointmentPhotoService;
use Illuminate\Http\Request;

class AppointmentPhotoController extends Controller
{
    public function __construct(private AppointmentPhotoService $photoService)
    {
    }

    public function index(Appointment $appointment)
    {
        $photos = AppointmentPhoto::where('appointment_id', $appointment->id)
            ->orderBy('type')
            ->orderByDesc('created_at')
            ->get();

        return response()->json($photos);
    }

    public function store(Request $request, Appointment $appointment)
    {
        $request->validate([
            'photo' => ['required', 'image', 'max:10240'],
            'type' => ['required', 'in:before,after,reference,other'],
            'caption' => ['nullable', 'string', 'max:500'],
        ]);

        $photo = $this->photoService->store(
            $request->file('photo'),
            $appointment,
            $request->type,
            $request->caption,
            auth()->user(),
        );

        return response()->json($photo);
    }

    public function update(Request $request, Appointment $appointment, AppointmentPhoto $photo)
    {
        $data = $request->validate([
            'caption' => ['nullable', 'string', 'max:500'],
            'is_visible_to_client' => ['nullable', 'boolean'],
        ]);

        $photo = $this->photoService->update($photo, $data);

        return response()->json($photo);
    }

    public function destroy(Appointment $appointment, AppointmentPhoto $photo)
    {
        $this->photoService->delete($photo);

        return response()->json(['success' => true]);
    }

    public function clientPhotos(string $clientId)
    {
        $grouped = $this->photoService->getClientPhotos($clientId);

        return response()->json($grouped);
    }
}
