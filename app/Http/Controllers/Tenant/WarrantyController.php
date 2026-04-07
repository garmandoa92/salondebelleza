<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\AppointmentWarranty;
use App\Services\WarrantyService;
use Illuminate\Http\Request;

class WarrantyController extends Controller
{
    public function __construct(private WarrantyService $warrantyService)
    {
    }

    public function check(Request $request)
    {
        $request->validate([
            'client_id' => ['required', 'uuid'],
            'service_id' => ['required', 'uuid'],
        ]);

        $warranty = $this->warrantyService->getActiveWarranty(
            $request->client_id,
            $request->service_id,
        );

        return response()->json([
            'has_warranty' => (bool) $warranty,
            'warranty' => $warranty?->load(['service:id,name', 'appointment:id,starts_at']),
        ]);
    }

    public function index(Request $request)
    {
        $query = AppointmentWarranty::with(['client:id,first_name,last_name', 'service:id,name'])
            ->orderByDesc('created_at');

        if ($request->filled('status')) $query->where('status', $request->status);
        if ($request->filled('date_from')) $query->whereDate('issued_at', '>=', $request->date_from);
        if ($request->filled('date_to')) $query->whereDate('issued_at', '<=', $request->date_to);

        return response()->json($query->paginate(25));
    }

    public function clientWarranties(string $clientId)
    {
        $warranties = AppointmentWarranty::where('client_id', $clientId)
            ->with(['service:id,name', 'appointment.stylist:id,name'])
            ->orderByDesc('issued_at')
            ->get();

        return response()->json($warranties);
    }

    public function void(Request $request, AppointmentWarranty $warranty)
    {
        $request->validate(['reason' => ['required', 'string', 'max:500']]);

        $this->warrantyService->void($warranty, $request->reason, auth()->user());

        return response()->json(['success' => true]);
    }

    public function expiring(Request $request)
    {
        $days = (int) $request->query('days', 30);

        $warranties = AppointmentWarranty::where('status', 'active')
            ->where('expires_at', '>', now())
            ->where('expires_at', '<=', now()->addDays($days))
            ->with(['client:id,first_name,last_name,phone', 'service:id,name'])
            ->orderBy('expires_at')
            ->get();

        return response()->json($warranties);
    }
}
