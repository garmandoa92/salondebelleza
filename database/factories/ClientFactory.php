<?php

namespace Database\Factories;

use App\Models\Client;
use Illuminate\Database\Eloquent\Factories\Factory;

class ClientFactory extends Factory
{
    protected $model = Client::class;

    public function definition(): array
    {
        $firstNames = [
            'Maria', 'Ana', 'Gabriela', 'Fernanda', 'Daniela',
            'Valentina', 'Camila', 'Sofia', 'Isabella', 'Lucia',
            'Adriana', 'Carmen', 'Patricia', 'Rosa', 'Monica',
            'Veronica', 'Paola', 'Andrea', 'Diana', 'Lorena',
        ];
        $lastNames = [
            'Garcia', 'Rodriguez', 'Martinez', 'Lopez', 'Gonzalez',
            'Perez', 'Sanchez', 'Ramirez', 'Torres', 'Flores',
            'Morales', 'Herrera', 'Castillo', 'Espinoza', 'Paredes',
            'Ortiz', 'Suarez', 'Reyes', 'Mendoza', 'Villacis',
        ];

        return [
            'first_name' => $this->faker->randomElement($firstNames),
            'last_name' => $this->faker->randomElement($lastNames),
            'phone' => '09' . $this->faker->unique()->numerify('########'),
            'email' => $this->faker->unique()->safeEmail(),
            'cedula' => $this->faker->numerify('##########'),
            'birthday' => $this->faker->dateTimeBetween('-60 years', '-18 years'),
            'notes' => $this->faker->optional(30)->sentence(),
            'allergies' => $this->faker->optional(10)->randomElement([
                'Alergia al latex', 'Sensibilidad al amoniaco',
                'Dermatitis de contacto', 'Alergia a tintes PPD',
            ]),
            'tags' => $this->faker->optional(40)->randomElements(['VIP', 'frecuente', 'nueva'], rand(1, 2)),
            'loyalty_points' => $this->faker->numberBetween(0, 500),
            'total_spent' => $this->faker->randomFloat(2, 0, 2000),
            'visit_count' => $this->faker->numberBetween(0, 50),
            'last_visit_at' => $this->faker->optional(70)->dateTimeBetween('-60 days', 'now'),
            'source' => $this->faker->randomElement(['walk_in', 'referral', 'instagram', 'whatsapp', 'website']),
            'is_active' => true,
        ];
    }
}
