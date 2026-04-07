<?php

namespace Database\Seeders;

use App\Models\Service;
use Illuminate\Database\Seeder;

class ServiceTypeSeeder extends Seeder
{
    public function run(): void
    {
        $keywords = [
            'hair' => [
                'corte', 'tinte', 'balayage', 'mechas', 'alisado', 'keratina',
                'hidratacion', 'hidratación', 'peinado', 'brushing', 'permanente',
                'decoloracion', 'decoloración', 'coloracion', 'coloración',
                'cabello', 'caballero', 'dama', 'infantil', 'flequillo',
                'barba', 'afeitado', 'degradado',
            ],
            'spa' => [
                'masaje', 'massage', 'relajante', 'descontracturante', 'deportivo',
                'drenaje', 'linfático', 'linfatico', 'piedras', 'calientes',
                'bambuterapia', 'reflexologia', 'reflexología', 'aromaterapia',
                'corporal', 'reduccion', 'reducción', 'anticelulitis', 'reafirmante',
                'envolvimiento', 'chocolate', 'ventosas', 'shiatsu', 'tailandes',
            ],
            'facial' => [
                'facial', 'limpieza', 'peeling', 'microdermoabrasion',
                'radiofrecuencia', 'ultrasonido', 'oxigenoterapia', 'acne', 'acné',
                'antienvejecimiento', 'lifting', 'botox', 'plasma',
            ],
            'nails' => [
                'manicure', 'manicura', 'pedicure', 'pedicura', 'uñas', 'unas',
                'semipermanente', 'gel', 'acrilico', 'acrílico', 'nail art',
                'esmaltado', 'spa de manos', 'spa de pies',
            ],
            'brows' => [
                'cejas', 'pestañas', 'pestanas', 'laminado', 'henna',
                'extensiones', 'lifting de pestañas', 'diseño de cejas',
                'microblading', 'depilacion', 'depilación', 'hilo',
            ],
        ];

        $services = Service::all();
        $updated = 0;

        foreach ($services as $service) {
            $nameLower = strtolower($service->name);
            $assigned = false;

            foreach ($keywords as $type => $words) {
                foreach ($words as $word) {
                    if (str_contains($nameLower, $word)) {
                        $service->update(['service_type' => $type]);
                        $assigned = true;
                        $updated++;
                        break 2;
                    }
                }
            }

            if (!$assigned) {
                $service->update(['service_type' => 'other']);
            }
        }

        $this->command->info("ServiceTypeSeeder: {$updated} servicios actualizados.");
    }
}
