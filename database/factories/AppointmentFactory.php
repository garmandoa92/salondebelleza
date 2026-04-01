<?php

namespace Database\Factories;

use App\Models\Appointment;
use App\Models\Client;
use App\Models\Service;
use App\Models\Stylist;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class AppointmentFactory extends Factory
{
    protected $model = Appointment::class;

    public function definition(): array
    {
        $startsAt = $this->faker->dateTimeBetween('-30 days', '+7 days');
        $duration = $this->faker->randomElement([30, 45, 60, 90, 120]);
        $endsAt = (clone $startsAt)->modify("+{$duration} minutes");

        $status = $startsAt < now()
            ? $this->faker->randomElement(['completed', 'cancelled', 'no_show'])
            : $this->faker->randomElement(['pending', 'confirmed']);

        return [
            'client_id' => Client::factory(),
            'stylist_id' => Stylist::factory(),
            'service_id' => Service::factory(),
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'status' => $status,
            'source' => $this->faker->randomElement(['manual', 'online_booking', 'whatsapp', 'phone']),
            'notes' => $this->faker->optional(20)->sentence(),
            'confirmed_at' => $status === 'confirmed' ? now() : null,
            'cancelled_at' => $status === 'cancelled' ? $startsAt : null,
            'cancelled_by' => $status === 'cancelled' ? $this->faker->randomElement(['client', 'staff']) : null,
            'created_by' => User::factory(),
        ];
    }
}
