<?php

namespace App\Http\Controllers\Central;

use App\Http\Controllers\Controller;
use App\Http\Requests\Central\RegisterRequest;
use App\Services\TenantService;
use Inertia\Inertia;

class RegisterController extends Controller
{
    public function __construct(
        private TenantService $tenantService
    ) {}

    public function create()
    {
        return Inertia::render('Central/Register');
    }

    public function store(RegisterRequest $request)
    {
        $result = $this->tenantService->createTenant($request->validated());

        $tenant = $result['tenant'];

        return redirect("/salon/{$tenant->id}/dashboard");
    }
}
