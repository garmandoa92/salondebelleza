<?php

namespace Database\Factories;

use App\Models\Commission;
use App\Models\SaleItem;
use App\Models\Stylist;
use Illuminate\Database\Eloquent\Factories\Factory;

class CommissionFactory extends Factory
{
    protected $model = Commission::class;

    public function definition(): array
    {
        $rate = $this->faker->randomElement([30, 35, 40, 45, 50]);
        $amount = $this->faker->randomFloat(2, 5, 80);

        return [
            'stylist_id' => Stylist::factory(),
            'sale_item_id' => SaleItem::factory(),
            'amount' => $amount,
            'rate' => $rate,
            'status' => 'pending',
            'period_start' => now()->startOfMonth(),
            'period_end' => now()->endOfMonth(),
        ];
    }
}
