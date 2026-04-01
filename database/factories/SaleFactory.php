<?php

namespace Database\Factories;

use App\Models\Client;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class SaleFactory extends Factory
{
    protected $model = Sale::class;

    public function definition(): array
    {
        $subtotal = $this->faker->randomFloat(2, 10, 200);
        $ivaRate = 15;
        $ivaAmount = round($subtotal * $ivaRate / 100, 2);
        $total = $subtotal + $ivaAmount;

        return [
            'client_id' => Client::factory(),
            'subtotal' => $subtotal,
            'discount_amount' => 0,
            'iva_rate' => $ivaRate,
            'iva_amount' => $ivaAmount,
            'total' => $total,
            'tip' => 0,
            'payment_methods' => [['method' => 'cash', 'amount' => $total]],
            'status' => 'completed',
            'completed_at' => now(),
            'completed_by' => User::factory(),
        ];
    }
}
