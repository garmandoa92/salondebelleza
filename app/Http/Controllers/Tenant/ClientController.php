<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\Client;
use App\Models\Sale;
use App\Models\Stylist;
use App\Services\ClientService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ClientController extends Controller
{
    public function __construct(
        private ClientService $clientService
    ) {}

    public function index(Request $request)
    {
        $clients = $this->clientService->getFiltered($request);
        $stylists = Stylist::where('is_active', true)->get(['id', 'name']);

        return Inertia::render('Clients/Index', [
            'clients' => $clients,
            'stylists' => $stylists,
            'filters' => $request->only('search', 'tag', 'stylist_id', 'inactive', 'sort', 'direction'),
        ]);
    }

    public function create()
    {
        return Inertia::render('Clients/Form', [
            'stylists' => Stylist::where('is_active', true)->get(['id', 'name']),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:20', 'unique:clients,phone'],
            'email' => ['nullable', 'email', 'max:255'],
            'cedula' => ['nullable', 'string', 'max:10'],
            'birthday' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'allergies' => ['nullable', 'string', 'max:1000'],
            'tags' => ['nullable', 'array'],
            'preferred_stylist_id' => ['nullable', 'uuid', 'exists:stylists,id'],
            'source' => ['nullable', 'string'],
        ]);

        $this->clientService->store($data);

        return redirect()->route('tenant.clients.index', ['tenant' => tenant('id')])
            ->with('success', 'Cliente creado.');
    }

    public function show(Client $client)
    {
        $client->load(['preferredStylist:id,name', 'healthProfile']);

        $appointments = Appointment::where('client_id', $client->id)
            ->with(['service:id,name,base_price,duration_minutes', 'stylist:id,name,color', 'sale:id,appointment_id,sri_invoice_id,total', 'diagnosis', 'sessionNote'])
            ->orderBy('starts_at', 'desc')
            ->get();

        $futureAppointments = $appointments->filter(fn ($a) => $a->starts_at->isFuture());
        $pastAppointments = $appointments->filter(fn ($a) => $a->starts_at->isPast());

        $sales = Sale::where('client_id', $client->id)
            ->with('items:id,sale_id,name,quantity,unit_price,subtotal')
            ->orderBy('created_at', 'desc')
            ->get();

        // Metrics
        $avgTicket = $sales->where('status', 'completed')->avg('total') ?? 0;
        $favoriteService = $pastAppointments
            ->where('status.value', 'completed')
            ->groupBy('service_id')
            ->sortByDesc(fn ($group) => $group->count())
            ->keys()
            ->first();
        $favoriteServiceName = $favoriteService ? $pastAppointments->firstWhere('service_id', $favoriteService)?->service?->name : null;

        return Inertia::render('Clients/Show', [
            'client' => $client,
            'pastAppointments' => $pastAppointments->values(),
            'futureAppointments' => $futureAppointments->values(),
            'sales' => $sales,
            'metrics' => [
                'total_visits' => $client->visit_count,
                'total_spent' => $client->total_spent,
                'avg_ticket' => round($avgTicket, 2),
                'favorite_service' => $favoriteServiceName,
            ],
        ]);
    }

    public function edit(Client $client)
    {
        return Inertia::render('Clients/Form', [
            'client' => $client,
            'stylists' => Stylist::where('is_active', true)->get(['id', 'name']),
        ]);
    }

    public function update(Request $request, Client $client)
    {
        $data = $request->validate([
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:20', "unique:clients,phone,{$client->id}"],
            'email' => ['nullable', 'email', 'max:255'],
            'cedula' => ['nullable', 'string', 'max:10'],
            'birthday' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'allergies' => ['nullable', 'string', 'max:1000'],
            'tags' => ['nullable', 'array'],
            'preferred_stylist_id' => ['nullable', 'uuid', 'exists:stylists,id'],
            'source' => ['nullable', 'string'],
        ]);

        $this->clientService->update($client, $data);

        return redirect()->route('tenant.clients.show', ['tenant' => tenant('id'), 'client' => $client->id])
            ->with('success', 'Cliente actualizado.');
    }

    public function destroy(Client $client)
    {
        $this->clientService->delete($client);

        return redirect()->route('tenant.clients.index', ['tenant' => tenant('id')])
            ->with('success', 'Cliente eliminado.');
    }
}
