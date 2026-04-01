<?php

namespace Database\Factories;

use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProductFactory extends Factory
{
    protected $model = Product::class;

    public function definition(): array
    {
        $type = $this->faker->randomElement(['use', 'sale']);
        $costPrice = $this->faker->randomFloat(2, 2, 50);

        return [
            'name' => $this->faker->randomElement([
                'Shampoo profesional 1L', 'Acondicionador keratina 500ml',
                'Tinte permanente', 'Oxidante 20 vol', 'Decolorante polvo',
                'Tratamiento capilar', 'Aceite de argan', 'Gel fijador',
                'Laca spray', 'Esmalte semipermanente', 'Removedor acetona',
                'Crema para manos', 'Base coat unas', 'Top coat unas',
                'Cera depilatoria', 'Pestanas postizas', 'Pegamento pestanas',
            ]),
            'sku' => strtoupper($this->faker->unique()->bothify('???-####')),
            'type' => $type,
            'unit' => $this->faker->randomElement(['ml', 'g', 'unit']),
            'cost_price' => $costPrice,
            'sale_price' => $type === 'sale' ? round($costPrice * 1.6, 2) : null,
            'stock' => $this->faker->randomFloat(0, 0, 100),
            'min_stock' => $this->faker->randomFloat(0, 1, 10),
            'supplier' => $this->faker->optional(60)->company(),
            'brand' => $this->faker->optional(70)->randomElement([
                'Loreal', 'Schwarzkopf', 'Wella', 'Kerastase', 'Matrix',
                'Redken', 'OPI', 'Essie', 'Revlon',
            ]),
            'is_active' => true,
        ];
    }
}
