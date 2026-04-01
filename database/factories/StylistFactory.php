<?php

namespace Database\Factories;

use App\Models\Stylist;
use Illuminate\Database\Eloquent\Factories\Factory;

class StylistFactory extends Factory
{
    protected $model = Stylist::class;

    public function definition(): array
    {
        $colors = ['#3B82F6', '#EF4444', '#10B981', '#F59E0B', '#8B5CF6', '#EC4899', '#14B8A6', '#F97316'];

        return [
            'name' => $this->faker->randomElement([
                'Maria Garcia', 'Carlos Lopez', 'Ana Martinez', 'Luis Paredes',
                'Sofia Morales', 'Diego Herrera', 'Valentina Ortiz', 'Andres Suarez',
                'Camila Reyes', 'Juan Espinoza',
            ]),
            'phone' => '09' . $this->faker->numerify('########'),
            'email' => $this->faker->unique()->safeEmail(),
            'bio' => $this->faker->sentence(),
            'specialties' => [],
            'commission_rules' => ['default' => 40],
            'schedule' => [
                'monday' => [['start' => '09:00', 'end' => '18:00']],
                'tuesday' => [['start' => '09:00', 'end' => '18:00']],
                'wednesday' => [['start' => '09:00', 'end' => '18:00']],
                'thursday' => [['start' => '09:00', 'end' => '18:00']],
                'friday' => [['start' => '09:00', 'end' => '18:00']],
                'saturday' => [['start' => '09:00', 'end' => '14:00']],
            ],
            'color' => $this->faker->randomElement($colors),
            'is_active' => true,
            'sort_order' => 0,
        ];
    }
}
