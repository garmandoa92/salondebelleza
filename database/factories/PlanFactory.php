<?php

namespace Database\Factories;

use App\Models\Plan;
use Illuminate\Database\Eloquent\Factories\Factory;

class PlanFactory extends Factory
{
    protected $model = Plan::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->word(),
            'slug' => $this->faker->unique()->slug(2),
            'price' => $this->faker->randomFloat(2, 10, 100),
            'billing_cycle' => 'monthly',
            'max_stylists' => $this->faker->numberBetween(1, 20),
            'max_branches' => 1,
            'features' => ['agenda', 'clientes'],
            'is_active' => true,
        ];
    }
}
