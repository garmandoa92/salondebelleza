<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\Client;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\Stylist;
use App\Services\AppointmentService;
use App\Services\AvailabilityService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AppointmentController extends Controller
{
    public function __construct(
        private AppointmentService $appointmentService,
        private AvailabilityService $availabilityService,
    ) {}

    public function index()
    {
        return Inertia::render('Agenda/Index', [
            'stylists' => Stylist::where('is_active', true)->orderBy('sort_order')->get(['id', 'name', 'color', 'photo_path']),
            'categories' => ServiceCategory::with('services:id,service_category_id,name,base_price,duration_minutes')->orderBy('sort_order')->get(['id', 'name', 'color']),
        ]);
    }

    public function events(Request $request)
    {
        $request->validate([
            'start' => ['required', 'date'],
            'end' => ['required', 'date'],
            'stylist_ids' => ['nullable', 'array'],
        ]);

        $events = $this->appointmentService->getEventsForCalendar(
            $request->start,
            $request->end,
            $request->stylist_ids,
        );

        return response()->json($events);
    }

    public function occupancy(Request $request)
    {
        $request->validate(['week_start' => ['required', 'date']]);

        return response()->json(
            $this->appointmentService->getWeekOccupancy($request->week_start)
        );
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'client_id' => ['required', 'uuid', 'exists:clients,id'],
            'stylist_id' => ['required', 'uuid', 'exists:stylists,id'],
            'service_id' => ['required', 'uuid', 'exists:services,id'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'source' => ['nullable', 'string'],
        ]);

        $data['created_by'] = auth()->id();
        $data['status'] = 'confirmed';
        $data['confirmed_at'] = now();
        $data['source'] = $data['source'] ?? 'manual';

        $appointment = $this->appointmentService->store($data);

        return back()->with('success', 'Cita creada.');
    }

    public function show(Appointment $appointment)
    {
        $appointment->load([
            'client:id,first_name,last_name,phone,email,allergies,tags,visit_count,last_visit_at,total_spent',
            'stylist:id,name,color,phone',
            'service:id,name,base_price,duration_minutes,service_category_id',
            'service.category:id,name,color',
            'creator:id,name',
        ]);

        return response()->json($appointment);
    }

    public function update(Request $request, Appointment $appointment)
    {
        $data = $request->validate([
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date'],
            'stylist_id' => ['nullable', 'uuid', 'exists:stylists,id'],
            'service_id' => ['nullable', 'uuid', 'exists:services,id'],
            'client_id' => ['nullable', 'uuid', 'exists:clients,id'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'internal_notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $this->appointmentService->update($appointment, array_filter($data, fn ($v) => $v !== null));

        return back()->with('success', 'Cita actualizada.');
    }

    public function destroy(Request $request, Appointment $appointment)
    {
        $request->validate([
            'reason' => ['required', 'string', 'max:500'],
            'cancelled_by' => ['nullable', 'string'],
        ]);

        $this->appointmentService->cancel(
            $appointment,
            $request->reason,
            $request->cancelled_by ?? 'staff',
        );

        return back()->with('success', 'Cita cancelada.');
    }

    public function confirm(Appointment $appointment)
    {
        $this->appointmentService->confirm($appointment);
        return back()->with('success', 'Cita confirmada.');
    }

    public function start(Appointment $appointment)
    {
        $this->appointmentService->start($appointment);
        return back()->with('success', 'Servicio iniciado.');
    }

    public function complete(Appointment $appointment)
    {
        $this->appointmentService->complete($appointment);
        return back()->with('success', 'Servicio completado.');
    }

    public function noShow(Appointment $appointment)
    {
        $this->appointmentService->markNoShow($appointment);
        return back()->with('success', 'Marcada como no-show.');
    }

    public function availability(Request $request)
    {
        $request->validate([
            'stylist_id' => ['required', 'uuid'],
            'service_id' => ['required', 'uuid'],
            'date' => ['required', 'date'],
        ]);

        $slots = $this->availabilityService->getAvailableSlots(
            $request->stylist_id,
            $request->service_id,
            $request->date,
        );

        return response()->json($slots);
    }

    public function searchClients(Request $request)
    {
        $q = $request->get('q', '');
        if (strlen($q) < 2) return response()->json([]);

        $clients = Client::where('first_name', 'like', "%{$q}%")
            ->orWhere('last_name', 'like', "%{$q}%")
            ->orWhere('phone', 'like', "%{$q}%")
            ->orWhere('cedula', 'like', "%{$q}%")
            ->limit(10)
            ->get(['id', 'first_name', 'last_name', 'phone', 'allergies', 'visit_count', 'last_visit_at']);

        return response()->json($clients);
    }

    public function storeClient(Request $request)
    {
        $data = $request->validate([
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:20', 'unique:clients,phone'],
            'email' => ['nullable', 'email', 'max:255'],
            'source' => ['nullable', 'string'],
        ]);

        $client = Client::create($data);

        return response()->json($client);
    }
}
