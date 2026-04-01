<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\StoreServiceRequest;
use App\Http\Requests\Tenant\UpdateServiceRequest;
use App\Models\Product;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Services\ServiceService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ServiceController extends Controller
{
    public function __construct(
        private ServiceService $serviceService
    ) {}

    public function index(Request $request)
    {
        $categories = $this->serviceService->getGroupedByCategory($request->search);

        return Inertia::render('Services/Index', [
            'categories' => $categories,
            'filters' => $request->only('search'),
        ]);
    }

    public function create()
    {
        return Inertia::render('Services/Form', [
            'categories' => ServiceCategory::orderBy('sort_order')->get(),
            'products' => Product::where('type', 'use')->where('is_active', true)->get(['id', 'name', 'unit']),
        ]);
    }

    public function store(StoreServiceRequest $request)
    {
        $this->serviceService->store(
            $request->safe()->except('image'),
            $request->file('image'),
        );

        return redirect()->route('tenant.services.index', ['tenant' => tenant('id')])
            ->with('success', 'Servicio creado.');
    }

    public function edit(Service $service)
    {
        return Inertia::render('Services/Form', [
            'service' => $service,
            'categories' => ServiceCategory::orderBy('sort_order')->get(),
            'products' => Product::where('type', 'use')->where('is_active', true)->get(['id', 'name', 'unit']),
        ]);
    }

    public function update(UpdateServiceRequest $request, Service $service)
    {
        $this->serviceService->update(
            $service,
            $request->safe()->except('image'),
            $request->file('image'),
        );

        return redirect()->route('tenant.services.index', ['tenant' => tenant('id')])
            ->with('success', 'Servicio actualizado.');
    }

    public function destroy(Service $service)
    {
        $this->serviceService->delete($service);

        return redirect()->route('tenant.services.index', ['tenant' => tenant('id')])
            ->with('success', 'Servicio eliminado.');
    }

    public function toggleActive(Service $service)
    {
        $this->serviceService->toggleActive($service);

        return back();
    }

    public function reorder(Request $request)
    {
        $request->validate(['ids' => ['required', 'array'], 'ids.*' => ['uuid']]);
        $this->serviceService->reorder($request->ids);

        return back();
    }
}
