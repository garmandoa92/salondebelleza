<?php

namespace Database\Factories;

use App\Models\ServiceCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

class ServiceCategoryFactory extends Factory
{
    protected $model = ServiceCategory::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->randomElement([
                'Corte y Estilo', 'Coloracion', 'Tratamientos',
                'Manicure y Pedicure', 'Cejas y Pestanas',
                'Maquillaje', 'Spa y Masajes',
            ]),
            'color' => $this->faker->hexColor(),
            'sort_order' => $this->faker->numberBetween(0, 10),
            'is_active' => true,
        ];
    }
}
