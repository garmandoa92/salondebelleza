<?php

namespace Database\Factories;

use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Service;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class SaleItemFactory extends Factory
{
    protected $model = SaleItem::class;

    public function definition(): array
    {
        $unitPrice = $this->faker->randomFloat(2, 5, 150);
        $quantity = 1;
        $subtotal = $unitPrice * $quantity;
        $ivaRate = 15;
        $ivaAmount = round($subtotal * $ivaRate / 100, 2);

        return [
            'sale_id' => Sale::factory(),
            'type' => 'service',
            'reference_id' => Str::uuid(),
            'name' => $this->faker->randomElement(['Corte de cabello', 'Tinte completo', 'Manicure', 'Brushing']),
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'discount_amount' => 0,
            'subtotal' => $subtotal,
            'iva_rate' => $ivaRate,
            'iva_amount' => $ivaAmount,
        ];
    }
}
