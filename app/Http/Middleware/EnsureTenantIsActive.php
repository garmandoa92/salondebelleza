<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTenantIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $tenant = tenant();

        if (! $tenant) {
            abort(404);
        }

        if (! $tenant->is_active) {
            abort(403, 'Este salón ha sido desactivado.');
        }

        return $next($request);
    }
}
