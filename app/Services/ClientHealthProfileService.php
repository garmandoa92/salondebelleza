<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\AppointmentHealthConfirmation;
use App\Models\ClientHealthProfile;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;

class ClientHealthProfileService
{
    public function getOrCreate(string $clientId): ClientHealthProfile
    {
        return ClientHealthProfile::firstOrCreate(
            ['client_id' => $clientId],
            [
                'allergies' => [],
                'medical_conditions' => [],
                'avoid_zones' => [],
                'personal_preferences' => [],
                'pressure_preference' => 2,
            ]
        );
    }

    public function update(ClientHealthProfile $profile, array $data): ClientHealthProfile
    {
        $profile->update([
            ...$data,
            'last_updated_by_user_id' => Auth::id(),
            'last_updated_by_client' => Carbon::now(),
        ]);

        return $profile->fresh();
    }

    public function confirmReading(Appointment $appointment): AppointmentHealthConfirmation
    {
        $existing = AppointmentHealthConfirmation::where('appointment_id', $appointment->id)
            ->where('user_id', Auth::id())
            ->first();

        if ($existing) {
            return $existing;
        }

        return AppointmentHealthConfirmation::create([
            'appointment_id' => $appointment->id,
            'user_id' => Auth::id(),
            'user_name' => Auth::user()->name,
            'confirmed_at' => Carbon::now(),
        ]);
    }

    public function isConfirmed(Appointment $appointment): bool
    {
        return AppointmentHealthConfirmation::where('appointment_id', $appointment->id)->exists();
    }

    public function getConfirmations(Appointment $appointment): \Illuminate\Support\Collection
    {
        return AppointmentHealthConfirmation::where('appointment_id', $appointment->id)
            ->orderBy('confirmed_at', 'desc')
            ->get();
    }
}
