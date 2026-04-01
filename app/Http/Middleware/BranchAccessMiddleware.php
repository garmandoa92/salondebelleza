<?php

namespace App\Http\Middleware;

use App\Models\Branch;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class BranchAccessMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        // Only enforce if tenant has multiple branches
        if (Branch::count() <= 1) {
            return $next($request);
        }

        $user = $request->user();
        if (! $user) {
            return $next($request);
        }

        // Owner and admin have access to all branches
        if ($user->hasRole(['owner', 'admin'])) {
            return $next($request);
        }

        // Branch manager: only their assigned branch
        $branchId = $request->header('X-Branch-Id') ?? $request->get('branch_id') ?? session('current_branch_id');

        if ($branchId && $user->hasRole('branch_manager')) {
            $branch = Branch::find($branchId);
            if ($branch && $branch->manager_user_id !== $user->id) {
                abort(403, 'No tienes acceso a esta sucursal.');
            }
        }

        return $next($request);
    }
}
