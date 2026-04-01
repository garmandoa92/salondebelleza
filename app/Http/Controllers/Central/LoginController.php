<?php

namespace App\Http\Controllers\Central;

use App\Http\Controllers\Controller;
use App\Models\TenantUser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;

class LoginController extends Controller
{
    public function create()
    {
        return Inertia::render('Central/Login');
    }

    public function store(Request $request)
    {
        $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ]);

        $tenantUser = TenantUser::where('email', $request->email)->first();

        if (! $tenantUser || ! Hash::check($request->password, $tenantUser->password)) {
            return back()->withErrors([
                'email' => 'Las credenciales no coinciden con nuestros registros.',
            ]);
        }

        $tenantUser->update(['last_login_at' => now()]);

        $tenant = $tenantUser->tenant;

        $token = encrypt([
            'email' => $tenantUser->email,
            'tenant_id' => $tenant->id,
            'expires' => now()->addMinutes(5)->timestamp,
        ]);

        return Inertia::location("/salon/{$tenant->id}/login?token={$token}");
    }

    public function destroy(Request $request)
    {
        Auth::guard('central')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home');
    }
}
