<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\ClientAdvance;
use App\Services\AdvanceService;
use Illuminate\Http\Request;

class AdvanceController extends Controller
{
    public function __construct(private AdvanceService $advanceService)
    {
    }

    public function index(Request $request)
    {
        $query = ClientAdvance::with(['client:id,first_name,last_name,phone', 'appointment.service:id,name', 'receiver:id,name'])
            ->orderByDesc('created_at');

        if ($request->filled('client_id')) {
            $query->where('client_id', $request->client_id);
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        return response()->json($query->paginate(25));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'client_id' => ['required', 'uuid', 'exists:clients,id'],
            'appointment_id' => ['nullable', 'uuid', 'exists:appointments,id'],
            'type' => ['required', 'in:advance,payment'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'payment_method' => ['required', 'in:cash,transfer,card_debit,card_credit,other'],
            'reference' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $advance = $this->advanceService->register($data, auth()->user());

        return response()->json([
            'success' => true,
            'advance' => $advance->load('client:id,first_name,last_name,balance'),
        ]);
    }

    public function clientAdvances(string $clientId)
    {
        $client = Client::findOrFail($clientId);

        $advances = ClientAdvance::where('client_id', $clientId)
            ->with(['appointment.service:id,name', 'receiver:id,name'])
            ->orderByDesc('created_at')
            ->get();

        return response()->json([
            'balance' => (float) $client->balance,
            'advances' => $advances,
        ]);
    }

    public function apply(Request $request, ClientAdvance $advance)
    {
        $data = $request->validate([
            'sale_id' => ['required', 'uuid', 'exists:sales,id'],
            'amount' => ['required', 'numeric', 'min:0.01'],
        ]);

        $sale = \App\Models\Sale::findOrFail($data['sale_id']);
        $this->advanceService->apply($advance, $sale, (float) $data['amount']);

        return response()->json(['success' => true]);
    }

    public function refund(Request $request, ClientAdvance $advance)
    {
        $notes = $request->input('notes');
        $this->advanceService->refund($advance, $notes);

        return response()->json(['success' => true]);
    }
}
