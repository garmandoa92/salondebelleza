<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\Client;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\Stylist;
use App\Models\User;
use App\Services\AvailabilityService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;

class BookingController extends Controller
{
    public function __construct(
        private AvailabilityService $availabilityService,
    ) {}

    public function index()
    {
        return Inertia::render('Public/Booking', [
            'tenant' => tenant(),
        ]);
    }

    public function services()
    {
        $categories = ServiceCategory::with(['services' => function ($q) {
            $q->where('is_visible', true)->orderBy('sort_order');
        }])
            ->orderBy('sort_order')
            ->get(['id', 'name', 'color']);

        return response()->json($categories);
    }

    public function stylists(Request $request)
    {
        $query = Stylist::where('is_active', true)->orderBy('sort_order');

        if ($request->service_id) {
            $query->whereHas('services', fn ($q) => $q->where('services.id', $request->service_id));
        }

        return response()->json(
            $query->get(['id', 'name', 'phone', 'photo_path', 'bio', 'specialties', 'color'])
        );
    }

    public function availability(Request $request)
    {
        $request->validate([
            'service_id' => ['required', 'uuid'],
            'stylist_id' => ['required', 'uuid'],
            'date' => ['required', 'date'],
        ]);

        $slots = $this->availabilityService->getAvailableSlots(
            $request->stylist_id,
            $request->service_id,
            $request->date,
        );

        return response()->json($slots);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'service_id' => ['required', 'uuid', 'exists:services,id'],
            'stylist_id' => ['required', 'uuid', 'exists:stylists,id'],
            'starts_at' => ['required', 'date'],
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:255'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $service = Service::findOrFail($data['service_id']);
        $startsAt = \Carbon\Carbon::parse($data['starts_at']);
        $endsAt = $startsAt->copy()->addMinutes($service->duration_minutes);

        // Find or create client
        $client = Client::firstOrCreate(
            ['phone' => $data['phone']],
            [
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'email' => $data['email'] ?? null,
                'source' => 'website',
            ],
        );

        // Get first admin user as creator
        $creator = User::first();

        $token = Str::random(64);

        $appointment = Appointment::create([
            'client_id' => $client->id,
            'stylist_id' => $data['stylist_id'],
            'service_id' => $data['service_id'],
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'status' => 'pending',
            'source' => 'online_booking',
            'notes' => $data['notes'] ?? null,
            'internal_notes' => "Token: {$token}",
            'created_by' => $creator->id,
        ]);

        return response()->json([
            'success' => true,
            'appointment' => [
                'id' => $appointment->id,
                'token' => $token,
                'service' => $service->name,
                'stylist' => Stylist::find($data['stylist_id'])->name,
                'date' => $startsAt->format('d/m/Y'),
                'time' => $startsAt->format('H:i'),
                'duration' => $service->duration_minutes,
            ],
        ]);
    }

    public function confirm(string $token)
    {
        $appointment = Appointment::where('internal_notes', 'like', "%Token: {$token}%")->first();

        if (! $appointment) {
            return Inertia::render('Public/BookingStatus', [
                'status' => 'error',
                'message' => 'Enlace de confirmacion invalido.',
            ]);
        }

        $appointment->update([
            'status' => 'confirmed',
            'confirmed_at' => now(),
        ]);

        return Inertia::render('Public/BookingStatus', [
            'status' => 'confirmed',
            'message' => 'Tu cita ha sido confirmada.',
            'appointment' => $appointment->load('service:id,name', 'stylist:id,name'),
        ]);
    }

    public function cancel(string $token)
    {
        $appointment = Appointment::where('internal_notes', 'like', "%Token: {$token}%")->first();

        if (! $appointment) {
            return Inertia::render('Public/BookingStatus', [
                'status' => 'error',
                'message' => 'Enlace invalido.',
            ]);
        }

        $appointment->update([
            'status' => 'cancelled',
            'cancelled_by' => 'client',
            'cancelled_at' => now(),
            'cancellation_reason' => 'Cancelada por el cliente desde el link',
        ]);

        return Inertia::render('Public/BookingStatus', [
            'status' => 'cancelled',
            'message' => 'Tu cita ha sido cancelada.',
        ]);
    }
}
