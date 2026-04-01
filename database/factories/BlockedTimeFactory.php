<?php

namespace Database\Factories;

use App\Models\BlockedTime;
use App\Models\Stylist;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class BlockedTimeFactory extends Factory
{
    protected $model = BlockedTime::class;

    public function definition(): array
    {
        $startsAt = $this->faker->dateTimeBetween('-7 days', '+14 days');
        $endsAt = (clone $startsAt)->modify('+' . $this->faker->randomElement([1, 2, 4, 8]) . ' hours');

        return [
            'stylist_id' => Stylist::factory(),
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'reason' => $this->faker->randomElement(['Almuerzo', 'Cita medica', 'Vacaciones', 'Capacitacion', 'Personal']),
            'is_recurring' => false,
            'created_by' => User::factory(),
        ];
    }
}
