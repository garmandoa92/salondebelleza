<?php

namespace App\Http\Middleware;

use App\Models\Branch;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    public function share(Request $request): array
    {
        $branches = [];
        $currentBranchId = null;

        if (tenant()) {
            try {
                $branches = Branch::where('is_active', true)->orderBy('sort_order')->get(['id', 'name', 'is_main'])->toArray();
                $currentBranchId = session('current_branch_id');
            } catch (\Throwable $e) {
                // Table may not exist yet
            }
        }

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $request->user(),
            ],
            'tenant' => tenant() ? [
                'id' => tenant()->id,
                'name' => tenant()->name,
                'slug' => tenant()->slug,
                'trial_ends_at' => tenant()->trial_ends_at?->toISOString(),
                'plan' => tenant()->plan?->name,
            ] : null,
            'branches' => $branches,
            'currentBranchId' => $currentBranchId,
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
            ],
        ];
    }
}
