<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\ClientPackage;
use App\Models\Package;
use App\Models\Service;
use App\Services\PackageService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class PackageController extends Controller
{
    public function __construct(
        private PackageService $packageService
    ) {}

    public function index()
    {
        $packages = Package::withCount(['clientPackages as active_clients' => fn ($q) => $q->where('status', 'active')])
            ->orderBy('sort_order')
            ->get();

        return Inertia::render('Packages/Index', [
            'packages' => $packages,
        ]);
    }

    public function create()
    {
        return Inertia::render('Packages/Form', [
            'services' => Service::orderBy('name')->get(['id', 'name', 'base_price']),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'price' => ['required', 'numeric', 'min:0'],
            'type' => ['required', 'in:sessions,combo'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.service_id' => ['required', 'uuid'],
            'items.*.service_name' => ['required', 'string'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'validity_days' => ['required', 'integer', 'min:1'],
        ]);

        Package::create($data);

        return redirect()->route('tenant.packages.index', ['tenant' => tenant('id')])
            ->with('success', 'Paquete creado.');
    }

    public function edit(Package $package)
    {
        return Inertia::render('Packages/Form', [
            'package' => $package,
            'services' => Service::orderBy('name')->get(['id', 'name', 'base_price']),
        ]);
    }

    public function update(Request $request, Package $package)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'price' => ['required', 'numeric', 'min:0'],
            'type' => ['required', 'in:sessions,combo'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.service_id' => ['required', 'uuid'],
            'items.*.service_name' => ['required', 'string'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'validity_days' => ['required', 'integer', 'min:1'],
            'is_active' => ['boolean'],
        ]);

        $package->update($data);

        return redirect()->route('tenant.packages.index', ['tenant' => tenant('id')])
            ->with('success', 'Paquete actualizado.');
    }

    public function destroy(Package $package)
    {
        if ($package->clientPackages()->where('status', 'active')->exists()) {
            $package->update(['is_active' => false]);
            return back()->with('success', 'Paquete desactivado (tiene clientes activos).');
        }

        $package->delete();
        return redirect()->route('tenant.packages.index', ['tenant' => tenant('id')])
            ->with('success', 'Paquete eliminado.');
    }

    // Check if client has active package for a service (API for appointment modal)
    public function checkClientPackage(Request $request)
    {
        $request->validate([
            'client_id' => ['required', 'uuid'],
            'service_id' => ['required', 'uuid'],
        ]);

        $item = $this->packageService->getActivePackageForClient($request->client_id, $request->service_id);

        if (! $item) {
            return response()->json(['has_package' => false]);
        }

        return response()->json([
            'has_package' => true,
            'package_name' => $item->clientPackage->package_name,
            'remaining' => $item->remaining,
            'expires_at' => $item->clientPackage->expires_at?->format('d/m/Y'),
            'client_package_item_id' => $item->id,
        ]);
    }

    public function useSession(Request $request)
    {
        $request->validate([
            'client_package_item_id' => ['required', 'uuid'],
            'appointment_id' => ['required', 'uuid'],
            'sessions_used' => ['nullable', 'integer', 'min:1'],
        ]);

        $item = \App\Models\ClientPackageItem::findOrFail($request->client_package_item_id);
        $sessions = $request->sessions_used ?? 1;
        $log = $this->packageService->useSessions($item, $sessions, $request->appointment_id, auth()->id());
        $cp = $item->clientPackage->fresh();

        return response()->json([
            'success' => true,
            'receipt_number' => $cp->receipt_number,
            'package_name' => $cp->package_name,
            'sessions_before' => $log->sessions_before,
            'sessions_after' => $log->sessions_after,
            'remaining' => $item->fresh()->remaining,
            'package_completed' => $cp->status === 'completed',
        ]);
    }

    public function clientPackages(string $clientId)
    {
        $packages = ClientPackage::where('client_id', $clientId)
            ->with('items')
            ->orderByDesc('purchased_at')
            ->get();

        // Attach usage logs
        $packageIds = $packages->pluck('id');
        $logs = \App\Models\PackageUsageLog::whereIn('client_package_id', $packageIds)
            ->with('user:id,name')
            ->orderByDesc('created_at')
            ->get()
            ->groupBy('client_package_id');

        $packages->each(function ($cp) use ($logs) {
            $cp->usage_logs = ($logs[$cp->id] ?? collect())->map(fn ($l) => [
                'date' => $l->created_at->format('d M Y'),
                'service_name' => $l->item?->service_name ?? '-',
                'sessions_used' => $l->sessions_used,
                'sessions_after' => $l->sessions_after,
                'total' => $l->item?->total_quantity ?? 0,
                'used_by' => $l->user?->name ?? '-',
            ]);
        });

        return response()->json($packages);
    }

    // For appointment modal: client's active packages + available packages to buy
    public function forAppointment(Request $request)
    {
        $request->validate(['client_id' => ['required', 'uuid']]);

        $clientActive = ClientPackage::where('client_id', $request->client_id)
            ->where('status', 'active')
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->with('items')
            ->get()
            ->map(fn ($cp) => [
                'id' => $cp->id,
                'receipt_number' => $cp->receipt_number,
                'package_name' => $cp->package_name,
                'purchased_at' => $cp->purchased_at?->format('d/m/Y'),
                'expires_at' => $cp->expires_at?->format('d/m/Y'),
                'items' => $cp->items->map(fn ($i) => [
                    'id' => $i->id,
                    'service_id' => $i->service_id,
                    'service_name' => $i->service_name,
                    'total' => $i->total_quantity,
                    'used' => $i->used_quantity,
                    'remaining' => $i->remaining,
                ]),
            ]);

        $availableToBuy = Package::where('is_active', true)->orderBy('name')->get([
            'id', 'name', 'price', 'type', 'items', 'validity_days',
        ]);

        return response()->json([
            'active' => $clientActive,
            'available' => $availableToBuy,
        ]);
    }
}
