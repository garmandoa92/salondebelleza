<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RedirectIfTrialExpired
{
    private array $except = [
        'tenant.upgrade',
        'tenant.logout',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $tenant = tenant();

        if (! $tenant) {
            return $next($request);
        }

        // Allow access to excepted routes
        if (in_array($request->route()?->getName(), $this->except)) {
            return $next($request);
        }

        // If tenant has active trial or subscription, allow access
        if ($tenant->isUsable()) {
            return $next($request);
        }

        return redirect()->route('tenant.upgrade');
    }
}
