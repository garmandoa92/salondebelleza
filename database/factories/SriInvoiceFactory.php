<?php

namespace Database\Factories;

use App\Models\SriInvoice;
use Illuminate\Database\Eloquent\Factories\Factory;

class SriInvoiceFactory extends Factory
{
    protected $model = SriInvoice::class;

    public function definition(): array
    {
        $subtotalIva = $this->faker->randomFloat(2, 10, 200);
        $ivaRate = 15;
        $ivaAmount = round($subtotalIva * $ivaRate / 100, 2);

        return [
            'invoice_type' => 'invoice',
            'establishment' => '001',
            'emission_point' => '001',
            'sequential' => str_pad((string) $this->faker->unique()->numberBetween(1, 999999999), 9, '0', STR_PAD_LEFT),
            'access_key' => $this->faker->unique()->numerify(str_repeat('#', 49)),
            'issue_date' => $this->faker->dateTimeBetween('-30 days', 'now'),
            'environment' => 'test',
            'buyer_identification_type' => 'final_consumer',
            'buyer_name' => $this->faker->name(),
            'subtotal_0' => 0,
            'subtotal_iva' => $subtotalIva,
            'iva_rate' => $ivaRate,
            'iva_amount' => $ivaAmount,
            'total' => $subtotalIva + $ivaAmount,
            'sri_status' => 'draft',
            'retry_count' => 0,
        ];
    }
}
