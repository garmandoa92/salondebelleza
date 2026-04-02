<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;
use Spatie\Permission\Models\Role;

class SettingsController extends Controller
{
    public function index()
    {
        $tenant = tenant();
        $users = User::with('roles')->where('is_active', true)->get();
        $roles = Role::all(['id', 'name']);

        return Inertia::render('Settings/Index', [
            'tenant' => $tenant,
            'settings' => $tenant->settings ?? [],
            'users' => $users,
            'roles' => $roles,
            'hasCertificate' => ! empty($tenant->settings['sri_certificate_uploaded']),
        ]);
    }

    public function updateSalon(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:20'],
            'address' => ['nullable', 'string', 'max:500'],
            'ruc' => ['nullable', 'string', 'size:13'],
            'razon_social' => ['nullable', 'string', 'max:255'],
            'inventory_mode' => ['nullable', 'in:centralized,per_branch'],
        ]);

        $tenant = tenant();
        $tenant->update([
            'name' => $data['name'],
            'phone' => $data['phone'] ?? $tenant->phone,
            'address' => $data['address'],
            'ruc' => $data['ruc'],
            'razon_social' => $data['razon_social'],
        ]);

        if (isset($data['inventory_mode'])) {
            $settings = $tenant->settings ?? [];
            $settings['inventory_mode'] = $data['inventory_mode'];
            $tenant->update(['settings' => $settings]);
        }

        return back()->with('success', 'Datos del salon actualizados.');
    }

    public function updateAppearance(Request $request)
    {
        $data = $request->validate([
            'primary_color' => ['required', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'accent_color' => ['required', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'bg_color' => ['required', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'text_color' => ['required', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
        ]);

        $tenant = tenant();
        $settings = $tenant->settings ?? [];
        $settings = array_merge($settings, $data);
        $tenant->update(['settings' => $settings]);

        return back()->with('success', 'Apariencia actualizada.');
    }

    public function updateSri(Request $request)
    {
        $data = $request->validate([
            'ambiente_sri' => ['required', 'in:test,production'],
            'establecimiento' => ['required', 'string', 'size:3'],
            'punto_emision' => ['required', 'string', 'size:3'],
            'regimen_tributario' => ['nullable', 'in:general,rimpe_emprendedor,rimpe_negocio_popular'],
            'obligado_contabilidad' => ['nullable', 'in:SI,NO'],
            'iva_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ]);

        $tenant = tenant();
        $settings = $tenant->settings ?? [];
        $settings = array_merge($settings, $data);
        $tenant->update(['settings' => $settings]);

        return back()->with('success', 'Configuracion SRI actualizada.');
    }

    public function uploadCertificate(Request $request)
    {
        $request->validate([
            'certificate' => ['required', 'file', 'max:10240'],
            'certificate_password' => ['required', 'string'],
        ]);

        $file = $request->file('certificate');
        $content = file_get_contents($file->getRealPath());

        // Validate the .p12 can be read
        $certs = [];
        if (! openssl_pkcs12_read($content, $certs, $request->certificate_password)) {
            return back()->withErrors(['certificate' => 'No se pudo leer el certificado. Verifique la contrasena.']);
        }

        $tenant = tenant();
        $settings = $tenant->settings ?? [];
        $settings['sri_certificate'] = Crypt::encrypt(base64_encode($content));
        $settings['sri_certificate_password'] = Crypt::encrypt($request->certificate_password);
        $settings['sri_certificate_uploaded'] = true;
        $settings['sri_certificate_uploaded_at'] = now()->toIso8601String();
        $tenant->update(['settings' => $settings]);

        return back()->with('success', 'Certificado SRI subido correctamente.');
    }

    public function updateSchedule(Request $request)
    {
        $data = $request->validate([
            'schedule' => ['required', 'array'],
            'holidays' => ['nullable', 'array'],
        ]);

        $tenant = tenant();
        $settings = $tenant->settings ?? [];
        $settings['salon_schedule'] = $data['schedule'];
        $settings['holidays'] = $data['holidays'] ?? [];
        $tenant->update(['settings' => $settings]);

        return back()->with('success', 'Horario actualizado.');
    }

    public function updateBooking(Request $request)
    {
        $data = $request->validate([
            'booking_enabled' => ['boolean'],
            'booking_min_advance_hours' => ['nullable', 'integer', 'min:0'],
            'booking_max_advance_days' => ['nullable', 'integer', 'min:1'],
            'booking_welcome_message' => ['nullable', 'string', 'max:500'],
            'booking_primary_color' => ['nullable', 'string'],
            'booking_cancellation_policy' => ['nullable', 'string', 'max:2000'],
        ]);

        $tenant = tenant();
        $settings = $tenant->settings ?? [];
        $settings = array_merge($settings, $data);
        $tenant->update(['settings' => $settings]);

        return back()->with('success', 'Configuracion de reservas actualizada.');
    }

    public function updateWhatsapp(Request $request)
    {
        $data = $request->validate([
            'whatsapp_api_key' => ['nullable', 'string'],
            'whatsapp_phone' => ['nullable', 'string'],
            'whatsapp_confirmations' => ['boolean'],
            'whatsapp_reminders' => ['boolean'],
            'whatsapp_invoices' => ['boolean'],
        ]);

        $tenant = tenant();
        $settings = $tenant->settings ?? [];
        $settings = array_merge($settings, $data);
        $tenant->update(['settings' => $settings]);

        return back()->with('success', 'Configuracion de WhatsApp actualizada.');
    }

    public function updatePayments(Request $request)
    {
        $data = $request->validate([
            'payment_methods_config' => ['nullable', 'array'],
        ]);

        $tenant = tenant();
        $settings = $tenant->settings ?? [];
        $settings['payment_methods_config'] = $data['payment_methods_config'] ?? [];
        $tenant->update(['settings' => $settings]);

        return back()->with('success', 'Metodos de pago actualizados.');
    }

    public function inviteUser(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:users,email'],
            'role' => ['required', 'string', 'exists:roles,name'],
        ]);

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make('password'),
            'is_active' => true,
        ]);
        $user->assignRole($data['role']);

        return back()->with('success', "Usuario {$data['name']} creado con rol {$data['role']}. Contrasena temporal: password");
    }

    public function toggleUser(User $user)
    {
        $user->update(['is_active' => ! $user->is_active]);

        return back()->with('success', $user->is_active ? 'Usuario activado.' : 'Usuario desactivado.');
    }
}
