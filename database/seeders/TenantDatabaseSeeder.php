<?php

namespace Database\Seeders;

use App\Models\ServiceCategory;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class TenantDatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Create Spatie roles
        $roles = ['owner', 'admin', 'receptionist', 'stylist', 'branch_manager', 'branch_receptionist'];
        foreach ($roles as $roleName) {
            Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);
        }

        // Create default service categories
        $categories = [
            ['name' => 'Corte y Estilo', 'color' => '#3B82F6', 'sort_order' => 1],
            ['name' => 'Coloracion', 'color' => '#F59E0B', 'sort_order' => 2],
            ['name' => 'Tratamientos', 'color' => '#10B981', 'sort_order' => 3],
            ['name' => 'Manicure y Pedicure', 'color' => '#EC4899', 'sort_order' => 4],
            ['name' => 'Cejas y Pestanas', 'color' => '#8B5CF6', 'sort_order' => 5],
            ['name' => 'Maquillaje', 'color' => '#EF4444', 'sort_order' => 6],
            ['name' => 'Spa y Masajes', 'color' => '#14B8A6', 'sort_order' => 7],
        ];

        foreach ($categories as $cat) {
            ServiceCategory::firstOrCreate(['name' => $cat['name']], $cat);
        }
    }
}
