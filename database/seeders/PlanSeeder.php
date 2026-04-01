<?php

namespace Database\Seeders;

use App\Models\Plan;
use Illuminate\Database\Seeder;

class PlanSeeder extends Seeder
{
    public function run(): void
    {
        $plans = [
            [
                'name' => 'Basico',
                'slug' => 'basico',
                'price' => 15.00,
                'billing_cycle' => 'monthly',
                'max_stylists' => 2,
                'max_branches' => 1,
                'features' => [
                    'agenda',
                    'clientes',
                    'servicios',
                    'caja',
                    'facturacion_basica',
                ],
                'is_active' => true,
            ],
            [
                'name' => 'Profesional',
                'slug' => 'profesional',
                'price' => 29.00,
                'billing_cycle' => 'monthly',
                'max_stylists' => 8,
                'max_branches' => 1,
                'features' => [
                    'agenda',
                    'clientes',
                    'servicios',
                    'caja',
                    'facturacion_completa',
                    'inventario',
                    'reportes',
                    'whatsapp',
                    'comisiones',
                    'booking_publico',
                ],
                'is_active' => true,
            ],
            [
                'name' => 'Cadena',
                'slug' => 'cadena',
                'price' => 59.00,
                'billing_cycle' => 'monthly',
                'max_stylists' => -1,
                'max_branches' => -1,
                'features' => [
                    'agenda',
                    'clientes',
                    'servicios',
                    'caja',
                    'facturacion_completa',
                    'inventario',
                    'reportes',
                    'whatsapp',
                    'comisiones',
                    'booking_publico',
                    'multisucursal',
                    'api_acceso',
                    'fideliacard',
                ],
                'is_active' => true,
            ],
        ];

        foreach ($plans as $plan) {
            Plan::updateOrCreate(
                ['slug' => $plan['slug']],
                $plan,
            );
        }
    }
}
