<?php

namespace Database\Factories;

use App\Models\Service;
use App\Models\ServiceCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

class ServiceFactory extends Factory
{
    protected $model = Service::class;

    public function definition(): array
    {
        return [
            'service_category_id' => ServiceCategory::factory(),
            'name' => $this->faker->randomElement([
                'Corte de cabello dama', 'Corte de cabello caballero',
                'Tinte completo', 'Mechas balayage', 'Alisado keratina',
                'Manicure clasico', 'Pedicure spa', 'Diseno de cejas',
                'Pestanas pelo a pelo', 'Maquillaje social',
                'Brushing', 'Tratamiento capilar', 'Decoloracion',
                'Unas acrilicas', 'Masaje relajante',
            ]),
            'description' => $this->faker->sentence(),
            'base_price' => $this->faker->randomFloat(2, 5, 150),
            'duration_minutes' => $this->faker->randomElement([15, 30, 45, 60, 90, 120]),
            'preparation_minutes' => $this->faker->randomElement([0, 5, 10, 15]),
            'recipe' => [],
            'is_visible' => true,
            'requires_consultation' => $this->faker->boolean(20),
            'sort_order' => $this->faker->numberBetween(0, 20),
        ];
    }
}
