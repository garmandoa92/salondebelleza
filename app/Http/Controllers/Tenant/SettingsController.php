<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use App\Models\SriInvoice;
use Inertia\Inertia;
use Spatie\Permission\Models\Role;

class SettingsController extends Controller
{
    public function index()
    {
        $tenant = tenant();
        $users = User::with('roles')->where('is_active', true)->get();
        $roles = Role::all(['id', 'name']);

        $settings = $tenant->settings ?? [];
        $est = $settings['establecimiento'] ?? '001';
        $pto = $settings['punto_emision'] ?? '001';

        return Inertia::render('Settings/Index', [
            'tenant' => $tenant,
            'settings' => $settings,
            'users' => $users,
            'roles' => $roles,
            'hasCertificate' => ! empty($settings['sri_certificate_uploaded']),
            'sequentials' => $this->getSequentials($est, $pto, $settings),
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

        // Validate the .p12 can be read (supports legacy SRI algorithms)
        $result = \App\Services\Sri\SriCertificateReader::read($content, $request->certificate_password);
        if ($result['error']) {
            return back()->withErrors(['certificate' => 'Error: ' . $result['error']]);
        }
        $certs = $result['certs'];

        // Extract certificate metadata
        $certInfo = [];
        if (! empty($certs['cert'])) {
            $certInfo = \App\Services\Sri\SriCertificateReader::extractInfo($certs['cert']);
        }

        $tenant = tenant();
        $settings = $tenant->settings ?? [];
        $settings['sri_certificate'] = Crypt::encrypt(base64_encode($content));
        $settings['sri_certificate_password'] = Crypt::encrypt($request->certificate_password);
        $settings['sri_certificate_uploaded'] = true;
        $settings['sri_certificate_uploaded_at'] = now()->toIso8601String();
        $settings['certificate_info'] = $certInfo;
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

    public function updatePrinter(Request $request)
    {
        $data = $request->validate([
            'printer_message' => ['nullable', 'string', 'max:120'],
            'printer_show_logo' => ['boolean'],
            'printer_paper_size' => ['required', 'in:58mm,80mm'],
        ]);

        $tenant = tenant();
        $settings = $tenant->settings ?? [];
        $settings = array_merge($settings, $data);
        $tenant->update(['settings' => $settings]);

        return back()->with('success', 'Configuracion de impresora actualizada.');
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

    public function updateSequential(Request $request)
    {
        $data = $request->validate([
            'type' => ['required', 'in:invoice,sales_note,credit_note'],
            'next_sequential' => ['required', 'string', 'size:9', 'regex:/^\d{9}$/'],
        ]);

        $settings = tenant()->settings ?? [];
        $est = $settings['establecimiento'] ?? '001';
        $pto = $settings['punto_emision'] ?? '001';

        // Validate not less than last emitted
        $lastSeq = \App\Models\SriInvoice::where('invoice_type', $data['type'])
            ->where('establishment', $est)
            ->where('emission_point', $pto)
            ->max('sequential') ?? '000000000';

        if ($data['next_sequential'] <= $lastSeq) {
            return back()->withErrors(['next_sequential' => "No puedes usar un numero menor o igual al ultimo emitido ({$lastSeq}). Esto causaria duplicados en el SRI."]);
        }

        $settings['sequential_override_' . $data['type']] = $data['next_sequential'];
        tenant()->update(['settings' => $settings]);

        return back()->with('success', 'Secuencial corregido.');
    }

    private function getSequentials(string $est, string $pto, array $settings): array
    {
        $types = [
            ['key' => 'invoice', 'label' => 'Facturas'],
            ['key' => 'sales_note', 'label' => 'Notas de venta'],
            ['key' => 'credit_note', 'label' => 'Notas de credito'],
        ];

        $monthStart = now()->startOfMonth()->toDateString();
        $result = [];

        foreach ($types as $type) {
            $lastSeq = \App\Models\SriInvoice::where('invoice_type', $type['key'])
                ->where('establishment', $est)
                ->where('emission_point', $pto)
                ->max('sequential') ?? '000000000';

            $override = $settings['sequential_override_' . $type['key']] ?? null;
            $nextSeq = $override ?? str_pad((int) $lastSeq + 1, 9, '0', STR_PAD_LEFT);

            $monthCount = \App\Models\SriInvoice::where('invoice_type', $type['key'])
                ->where('establishment', $est)
                ->where('emission_point', $pto)
                ->where('issue_date', '>=', $monthStart)
                ->count();

            $lastInvoice = \App\Models\SriInvoice::where('invoice_type', $type['key'])
                ->where('establishment', $est)
                ->where('emission_point', $pto)
                ->orderByDesc('created_at')
                ->first(['sequential', 'issue_date', 'total']);

            $result[] = [
                'key' => $type['key'],
                'label' => $type['label'],
                'last_sequential' => $lastSeq,
                'next_sequential' => $nextSeq,
                'has_override' => (bool) $override,
                'month_count' => $monthCount,
                'last_invoice' => $lastInvoice ? [
                    'sequential' => $lastInvoice->sequential,
                    'date' => $lastInvoice->issue_date,
                    'total' => $lastInvoice->total,
                ] : null,
            ];
        }

        return $result;
    }
}
