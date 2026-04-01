<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class StockMovementFactory extends Factory
{
    protected $model = StockMovement::class;

    public function definition(): array
    {
        $type = $this->faker->randomElement(['purchase', 'consumption', 'adjustment', 'initial']);

        return [
            'product_id' => Product::factory(),
            'type' => $type,
            'quantity' => $type === 'consumption' ? -$this->faker->randomFloat(1, 1, 10) : $this->faker->randomFloat(1, 1, 50),
            'unit_cost' => $this->faker->optional(60)->randomFloat(2, 2, 50),
            'notes' => $this->faker->optional(30)->sentence(),
            'created_by' => User::factory(),
        ];
    }
}
