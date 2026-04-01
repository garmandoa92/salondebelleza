<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class TenantDatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Create Spatie roles for this tenant
        $roles = ['owner', 'admin', 'receptionist', 'stylist'];
        foreach ($roles as $roleName) {
            Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);
        }
    }
}
