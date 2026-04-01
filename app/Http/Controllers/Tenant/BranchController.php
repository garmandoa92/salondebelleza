<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Plan;
use App\Models\Stylist;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;

class BranchController extends Controller
{
    public function index()
    {
        $branches = Branch::with('manager:id,name')
            ->withCount([
                'appointments as appointments_today' => function ($q) {
                    $q->whereDate('starts_at', today());
                },
                'stylists',
            ])
            ->orderBy('sort_order')
            ->get();

        $canCreate = $this->canCreateBranch();

        return Inertia::render('Branches/Index', [
            'branches' => $branches,
            'canCreate' => $canCreate,
        ]);
    }

    public function create()
    {
        if (! $this->canCreateBranch()) {
            return redirect()->route('tenant.branches.index', ['tenant' => tenant('id')])
                ->with('error', 'Tu plan no permite mas sucursales. Actualiza al plan Cadena.');
        }

        return Inertia::render('Branches/Form', [
            'users' => User::where('is_active', true)->get(['id', 'name']),
            'stylists' => Stylist::where('is_active', true)->get(['id', 'name']),
        ]);
    }

    public function store(Request $request)
    {
        if (! $this->canCreateBranch()) {
            return back()->withErrors(['plan' => 'Tu plan no permite mas sucursales.']);
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:500'],
            'phone' => ['nullable', 'string', 'max:20'],
            'email' => ['nullable', 'email'],
            'manager_user_id' => ['nullable', 'uuid', 'exists:users,id'],
            'schedule' => ['nullable', 'array'],
            'sri_establishment' => ['nullable', 'string', 'size:3'],
            'sri_emission_point' => ['nullable', 'string', 'size:3'],
            'stylist_ids' => ['nullable', 'array'],
            'stylist_ids.*' => ['uuid'],
        ]);

        $isFirst = Branch::count() === 0;
        $branch = Branch::create(array_merge(
            collect($data)->except('stylist_ids')->toArray(),
            ['is_main' => $isFirst],
        ));

        if (! empty($data['stylist_ids'])) {
            $branch->stylists()->attach($data['stylist_ids']);
        }

        return redirect()->route('tenant.branches.index', ['tenant' => tenant('id')])
            ->with('success', 'Sucursal creada.');
    }

    public function edit(Branch $branch)
    {
        $branch->load('stylists:id,name');

        return Inertia::render('Branches/Form', [
            'branch' => $branch,
            'users' => User::where('is_active', true)->get(['id', 'name']),
            'stylists' => Stylist::where('is_active', true)->get(['id', 'name']),
        ]);
    }

    public function update(Request $request, Branch $branch)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:500'],
            'phone' => ['nullable', 'string', 'max:20'],
            'email' => ['nullable', 'email'],
            'manager_user_id' => ['nullable', 'uuid', 'exists:users,id'],
            'schedule' => ['nullable', 'array'],
            'sri_establishment' => ['nullable', 'string', 'size:3'],
            'sri_emission_point' => ['nullable', 'string', 'size:3'],
            'is_active' => ['boolean'],
            'stylist_ids' => ['nullable', 'array'],
        ]);

        $branch->update(collect($data)->except('stylist_ids')->toArray());

        if (isset($data['stylist_ids'])) {
            $branch->stylists()->sync($data['stylist_ids']);
        }

        return redirect()->route('tenant.branches.index', ['tenant' => tenant('id')])
            ->with('success', 'Sucursal actualizada.');
    }

    public function destroy(Branch $branch)
    {
        if ($branch->appointments()->exists()) {
            $branch->update(['is_active' => false]);
            return back()->with('success', 'Sucursal desactivada (tiene historial).');
        }

        $branch->delete();
        return redirect()->route('tenant.branches.index', ['tenant' => tenant('id')])
            ->with('success', 'Sucursal eliminada.');
    }

    private function canCreateBranch(): bool
    {
        $tenant = tenant();
        $plan = $tenant->plan;

        if (! $plan) return true; // No plan restrictions during trial

        $currentCount = Branch::count();
        return $plan->max_branches === -1 || $currentCount < $plan->max_branches;
    }
}
