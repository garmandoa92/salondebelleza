<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\StoreStylistRequest;
use App\Http\Requests\Tenant\UpdateStylistRequest;
use App\Models\BlockedTime;
use App\Models\ServiceCategory;
use App\Models\Stylist;
use App\Services\StylistService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class StylistController extends Controller
{
    public function __construct(
        private StylistService $stylistService
    ) {}

    public function index()
    {
        $stylists = Stylist::withCount([
            'appointments as appointments_this_month' => function ($q) {
                $q->whereBetween('starts_at', [now()->startOfMonth(), now()->endOfMonth()]);
            },
        ])->orderBy('sort_order')->get();

        return Inertia::render('Stylists/Index', [
            'stylists' => $stylists,
        ]);
    }

    public function create()
    {
        return Inertia::render('Stylists/Form', [
            'categories' => ServiceCategory::orderBy('sort_order')->get(),
            'branches' => \App\Models\Branch::where('is_active', true)->orderBy('sort_order')->get(['id', 'name']),
        ]);
    }

    public function store(StoreStylistRequest $request)
    {
        $this->stylistService->store(
            $request->safe()->except('photo'),
            $request->file('photo'),
        );

        return redirect()->route('tenant.stylists.index', ['tenant' => tenant('id')])
            ->with('success', 'Estilista creado.');
    }

    public function edit(Stylist $stylist)
    {
        $stylist->load('branches:id,name');

        return Inertia::render('Stylists/Form', [
            'stylist' => $stylist,
            'categories' => ServiceCategory::orderBy('sort_order')->get(),
            'branches' => \App\Models\Branch::where('is_active', true)->orderBy('sort_order')->get(['id', 'name']),
        ]);
    }

    public function update(UpdateStylistRequest $request, Stylist $stylist)
    {
        $this->stylistService->update(
            $stylist,
            $request->safe()->except('photo'),
            $request->file('photo'),
        );

        return redirect()->route('tenant.stylists.index', ['tenant' => tenant('id')])
            ->with('success', 'Estilista actualizado.');
    }

    public function destroy(Stylist $stylist)
    {
        $this->stylistService->delete($stylist);

        return redirect()->route('tenant.stylists.index', ['tenant' => tenant('id')])
            ->with('success', 'Estilista eliminado.');
    }

    public function toggleActive(Stylist $stylist)
    {
        $this->stylistService->toggleActive($stylist);

        return back();
    }

    public function schedule(Stylist $stylist)
    {
        $blockedTimes = BlockedTime::where('stylist_id', $stylist->id)
            ->orWhereNull('stylist_id')
            ->with('creator:id,name')
            ->orderBy('starts_at', 'desc')
            ->get();

        return Inertia::render('Stylists/Schedule', [
            'stylist' => $stylist,
            'blockedTimes' => $blockedTimes,
        ]);
    }
}
