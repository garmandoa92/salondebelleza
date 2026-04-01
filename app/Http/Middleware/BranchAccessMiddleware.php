<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class BranchAccessMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        // Stub for Phase 3 (Session 13 - Multi-branch)
        // Will verify user has access to the requested branch
        return $next($request);
    }
}
