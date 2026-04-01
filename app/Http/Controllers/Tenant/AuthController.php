<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;

class AuthController extends Controller
{
    public function showLogin(Request $request)
    {
        // Handle cross-domain login token from central
        if ($request->has('token')) {
            try {
                $data = decrypt($request->token);
                if ($data['expires'] > now()->timestamp) {
                    $user = User::where('email', $data['email'])->first();
                    if ($user) {
                        Auth::login($user);
                        $user->update(['last_login_at' => now()]);
                        return redirect()->route('tenant.dashboard', ['tenant' => tenant('id')]);
                    }
                }
            } catch (\Exception $e) {
                // Invalid token, show login form
            }
        }

        return Inertia::render('Tenant/Login');
    }

    public function login(Request $request)
    {
        $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::where('email', $request->email)->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            return back()->withErrors([
                'email' => 'Las credenciales no coinciden con nuestros registros.',
            ]);
        }

        if (! $user->is_active) {
            return back()->withErrors([
                'email' => 'Tu cuenta ha sido desactivada.',
            ]);
        }

        Auth::login($user, $request->boolean('remember'));
        $user->update(['last_login_at' => now()]);

        $request->session()->regenerate();

        return redirect()->intended(route('tenant.dashboard', ['tenant' => tenant('id')]));
    }

    public function logout(Request $request)
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('tenant.login', ['tenant' => tenant('id')]);
    }
}
