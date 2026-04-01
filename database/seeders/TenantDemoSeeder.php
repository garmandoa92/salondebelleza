<?php

namespace Database\Seeders;

use App\Models\Appointment;
use App\Models\Client;
use App\Models\Product;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\Stylist;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class TenantDemoSeeder extends Seeder
{
    public function run(): void
    {
        // Ensure roles exist
        foreach (['owner', 'admin', 'receptionist', 'stylist'] as $roleName) {
            Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);
        }

        // Get or create admin user
        $admin = User::firstOrCreate(
            ['email' => 'admin@demo.test'],
            ['name' => 'Admin', 'password' => Hash::make('password'), 'is_active' => true],
        );
        if (!$admin->hasRole('owner')) {
            $admin->assignRole('owner');
        }

        // Create 3 stylists with user accounts
        $stylistsData = [
            ['name' => 'Maria Garcia', 'email' => 'maria@demo.test', 'phone' => '0991234501', 'color' => '#EC4899'],
            ['name' => 'Carlos Lopez', 'email' => 'carlos@demo.test', 'phone' => '0991234502', 'color' => '#3B82F6'],
            ['name' => 'Ana Martinez', 'email' => 'ana@demo.test', 'phone' => '0991234503', 'color' => '#10B981'],
        ];

        $stylists = [];
        foreach ($stylistsData as $data) {
            $user = User::firstOrCreate(
                ['email' => $data['email']],
                ['name' => $data['name'], 'password' => Hash::make('password'), 'is_active' => true],
            );
            if (!$user->hasRole('stylist')) {
                $user->assignRole('stylist');
            }

            $stylist = Stylist::firstOrCreate(
                ['email' => $data['email']],
                [
                    'user_id' => $user->id,
                    'name' => $data['name'],
                    'phone' => $data['phone'],
                    'email' => $data['email'],
                    'color' => $data['color'],
                    'commission_rules' => ['default' => 40],
                    'schedule' => [
                        'monday' => [['start' => '09:00', 'end' => '18:00']],
                        'tuesday' => [['start' => '09:00', 'end' => '18:00']],
                        'wednesday' => [['start' => '09:00', 'end' => '18:00']],
                        'thursday' => [['start' => '09:00', 'end' => '18:00']],
                        'friday' => [['start' => '09:00', 'end' => '18:00']],
                        'saturday' => [['start' => '09:00', 'end' => '14:00']],
                    ],
                    'is_active' => true,
                ],
            );
            $stylists[] = $stylist;
        }

        // Create services (2 per main category)
        $categories = ServiceCategory::all();
        $servicesData = [
            'Corte y Estilo' => [
                ['name' => 'Corte de cabello dama', 'base_price' => 15, 'duration_minutes' => 45],
                ['name' => 'Corte de cabello caballero', 'base_price' => 8, 'duration_minutes' => 30],
            ],
            'Coloracion' => [
                ['name' => 'Tinte completo', 'base_price' => 35, 'duration_minutes' => 90],
                ['name' => 'Mechas balayage', 'base_price' => 60, 'duration_minutes' => 120],
            ],
            'Tratamientos' => [
                ['name' => 'Alisado keratina', 'base_price' => 80, 'duration_minutes' => 120],
                ['name' => 'Tratamiento capilar profundo', 'base_price' => 25, 'duration_minutes' => 45],
            ],
            'Manicure y Pedicure' => [
                ['name' => 'Manicure clasico', 'base_price' => 10, 'duration_minutes' => 30],
                ['name' => 'Pedicure spa', 'base_price' => 18, 'duration_minutes' => 45],
            ],
        ];

        $services = [];
        foreach ($servicesData as $categoryName => $items) {
            $category = $categories->firstWhere('name', $categoryName);
            if (!$category) continue;

            foreach ($items as $item) {
                $service = Service::firstOrCreate(
                    ['name' => $item['name']],
                    array_merge($item, ['service_category_id' => $category->id]),
                );
                $services[] = $service;

                // Attach all stylists to each service
                foreach ($stylists as $stylist) {
                    $service->stylists()->syncWithoutDetaching([$stylist->id]);
                }
            }
        }

        // Create 20 clients
        if (Client::count() < 20) {
            Client::factory(20 - Client::count())->create([
                'preferred_stylist_id' => fn() => $stylists[array_rand($stylists)]->id,
            ]);
        }

        $clients = Client::all();

        // Create 30 past appointments (completed, cancelled, no_show) + 5 future
        if (Appointment::count() === 0 && $services && $clients->isNotEmpty()) {
            // 30 past appointments
            for ($i = 0; $i < 30; $i++) {
                $startsAt = now()->subDays(rand(1, 30))->setHour(rand(9, 17))->setMinute(rand(0, 3) * 15)->setSecond(0);
                $service = $services[array_rand($services)];
                $duration = $service->duration_minutes;

                Appointment::create([
                    'client_id' => $clients->random()->id,
                    'stylist_id' => $stylists[array_rand($stylists)]->id,
                    'service_id' => $service->id,
                    'starts_at' => $startsAt,
                    'ends_at' => (clone $startsAt)->addMinutes($duration),
                    'status' => collect(['completed', 'completed', 'completed', 'completed', 'cancelled', 'no_show'])->random(),
                    'source' => collect(['manual', 'online_booking', 'whatsapp', 'phone'])->random(),
                    'created_by' => $admin->id,
                ]);
            }

            // 5 future appointments
            for ($i = 0; $i < 5; $i++) {
                $startsAt = now()->addDays(rand(1, 7))->setHour(rand(9, 17))->setMinute(rand(0, 3) * 15)->setSecond(0);
                $service = $services[array_rand($services)];

                Appointment::create([
                    'client_id' => $clients->random()->id,
                    'stylist_id' => $stylists[array_rand($stylists)]->id,
                    'service_id' => $service->id,
                    'starts_at' => $startsAt,
                    'ends_at' => (clone $startsAt)->addMinutes($service->duration_minutes),
                    'status' => 'confirmed',
                    'source' => 'manual',
                    'confirmed_at' => now(),
                    'created_by' => $admin->id,
                ]);
            }
        }

        // Create some products
        if (Product::count() === 0) {
            Product::factory(10)->create();
        }
    }
}
