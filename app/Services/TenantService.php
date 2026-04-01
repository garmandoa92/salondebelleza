<?php

namespace App\Services;

use App\Models\Plan;
use App\Models\Tenant;
use App\Models\TenantUser;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class TenantService
{
    public function createTenant(array $data): array
    {
        $plan = Plan::where('slug', $data['plan'] ?? 'profesional')->firstOrFail();

        // Create tenant (this triggers CreateDatabase + MigrateDatabase + SeedDatabase pipeline)
        // Use slug as tenant ID for clean database names (tenant_demo, tenant_salon1, etc.)
        $tenant = Tenant::create([
            'id' => $data['slug'],
            'name' => $data['salon_name'],
            'slug' => $data['slug'],
            'phone' => $data['phone'] ?? '',
            'plan_id' => $plan->id,
            'trial_ends_at' => now()->addDays(30),
            'settings' => [
                'timezone' => 'America/Guayaquil',
                'currency' => 'USD',
                'fideliacard_enabled' => false,
            ],
            'is_active' => true,
        ]);

        $tenant->domains()->create([
            'domain' => $data['slug'],
        ]);

        $tenantUser = TenantUser::create([
            'tenant_id' => $tenant->id,
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'role' => 'owner',
        ]);

        // Create the owner user inside the tenant DB
        $tenant->run(function () use ($data) {
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => Hash::make($data['password']),
                'is_active' => true,
            ]);
            $user->assignRole('owner');
        });

        return [
            'tenant' => $tenant,
            'tenantUser' => $tenantUser,
        ];
    }
}
