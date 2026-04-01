<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class TenantDemoSeeder extends Seeder
{
    public function run(): void
    {
        // Create roles
        $roles = ['owner', 'admin', 'receptionist', 'stylist'];
        foreach ($roles as $roleName) {
            Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);
        }

        // Create stylists (users with stylist role)
        $stylists = [
            ['name' => 'Maria Garcia', 'email' => 'maria@demo.test'],
            ['name' => 'Carlos Lopez', 'email' => 'carlos@demo.test'],
            ['name' => 'Ana Martinez', 'email' => 'ana@demo.test'],
        ];

        foreach ($stylists as $data) {
            $user = User::firstOrCreate(
                ['email' => $data['email']],
                [
                    'name' => $data['name'],
                    'password' => Hash::make('password'),
                    'is_active' => true,
                ],
            );
            $user->assignRole('stylist');
        }

        // Note: Services, clients, and appointments will be created
        // in sessions 2-4 when those models exist.
        // For now, we only seed users and roles.
    }
}
