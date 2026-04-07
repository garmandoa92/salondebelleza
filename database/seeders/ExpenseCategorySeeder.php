<?php

namespace Database\Seeders;

use App\Models\ExpenseCategory;
use Illuminate\Database\Seeder;

class ExpenseCategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            ['name' => 'Arriendo / Local', 'icon' => 'home', 'color' => '#4A7C6F', 'sort_order' => 1],
            ['name' => 'Servicios básicos', 'icon' => 'bolt', 'color' => '#C9A96E', 'sort_order' => 2],
            ['name' => 'Sueldos y salarios', 'icon' => 'users', 'color' => '#3B82F6', 'sort_order' => 3],
            ['name' => 'Beneficios sociales', 'icon' => 'gift', 'color' => '#8B5CF6', 'sort_order' => 4],
            ['name' => 'Productos e insumos', 'icon' => 'beaker', 'color' => '#10B981', 'sort_order' => 5],
            ['name' => 'Publicidad y marketing', 'icon' => 'megaphone', 'color' => '#F59E0B', 'sort_order' => 6],
            ['name' => 'Equipos y herramientas', 'icon' => 'wrench-screwdriver', 'color' => '#6366F1', 'sort_order' => 7],
            ['name' => 'Mantenimiento', 'icon' => 'cog', 'color' => '#84CC16', 'sort_order' => 8],
            ['name' => 'Transporte y movilización', 'icon' => 'truck', 'color' => '#F97316', 'sort_order' => 9],
            ['name' => 'Otros gastos', 'icon' => 'ellipsis-horizontal', 'color' => '#9CA3AF', 'sort_order' => 10],
        ];

        foreach ($categories as $cat) {
            ExpenseCategory::firstOrCreate(
                ['name' => $cat['name']],
                [...$cat, 'is_system' => true]
            );
        }
    }
}
