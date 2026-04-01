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

    // Use a package session (called when completing appointment)
    public function useSession(Request $request)
    {
        $request->validate([
            'client_package_item_id' => ['required', 'uuid'],
            'appointment_id' => ['required', 'uuid'],
        ]);

        $item = \App\Models\ClientPackageItem::findOrFail($request->client_package_item_id);
        $this->packageService->useSession($item, $request->appointment_id);

        return response()->json(['success' => true, 'remaining' => $item->fresh()->remaining]);
    }

    // Client packages for ficha del cliente
    public function clientPackages(string $clientId)
    {
        $packages = ClientPackage::where('client_id', $clientId)
            ->with('items')
            ->orderByDesc('purchased_at')
            ->get();

        return response()->json($packages);
    }
}
