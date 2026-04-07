<?php

namespace App\Constants;

class HealthProfileConstants
{
    const ALLERGIES = [
        'Látex',
        'Frutos secos',
        'Mariscos',
        'Productos químicos',
        'Perfumes y fragancias',
        'Polen',
        'Polvo',
        'Metales (níquel)',
        'Aceites esenciales',
        'Peróxido de hidrógeno',
    ];

    const MEDICAL_CONDITIONS = [
        'Hipertensión',
        'Hipotensión',
        'Diabetes',
        'Embarazo',
        'Problemas de piel',
        'Marcapasos',
        'Epilepsia',
        'Osteoporosis',
        'Várices',
        'Problemas cardíacos',
        'Cáncer (en tratamiento)',
        'Enfermedades autoinmunes',
        'Tiroides',
        'Lesiones recientes',
    ];

    const PERSONAL_PREFERENCES = [
        'Música relajante',
        'Silencio total',
        'Sin conversación',
        'Temperatura cálida',
        'Temperatura fresca',
        'Aromas cítricos',
        'Aromas florales',
        'Sin aromaterapia',
        'Luz tenue',
    ];

    const TECHNIQUES = [
        'Effleurage',
        'Petrissage',
        'Friccion',
        'Tapotement',
        'Vibracion',
        'Puntos gatillo',
        'Drenaje linfatico',
        'Piedras calientes',
        'Reflexologia',
        'Aromaterapia',
        'Bambuterapia',
        'Ventosas',
        'Masaje con velas',
        'Masaje tailandes',
        'Shiatsu',
    ];

    const TECHNIQUES_BY_TYPE = [
        'hair' => [
            'Corte recto', 'Degradado', 'Navaja', 'Tijera',
            'Balayage', 'Mechas', 'Tinte completo', 'Raiz',
            'Alisado', 'Keratina', 'Hidratacion profunda',
            'Permanente', 'Brushing', 'Babylights',
        ],
        'spa' => [
            'Effleurage', 'Petrissage', 'Friccion', 'Tapotement',
            'Vibracion', 'Puntos gatillo', 'Drenaje linfatico',
            'Piedras calientes', 'Bambuterapia', 'Reflexologia',
            'Aromaterapia', 'Ventosas', 'Shiatsu', 'Tailandes',
            'Masaje con velas',
        ],
        'facial' => [
            'Limpieza profunda', 'Exfoliacion', 'Extraccion',
            'Hidratacion', 'Peeling quimico', 'Microdermoabrasion',
            'Radiofrecuencia', 'Ultrasonido', 'Oxigenoterapia',
            'Mesoterapia', 'Plasma', 'LED terapia',
        ],
        'nails' => [
            'Manicure clasica', 'Manicure semipermanente',
            'Manicure gel', 'Pedicure clasico', 'Pedicure spa',
            'Pedicure semipermanente', 'Nail art', 'Acrilico',
            'Relleno', 'Retiro de gel',
        ],
        'brows' => [
            'Diseno con hilo', 'Diseno con pinza', 'Diseno con cera',
            'Henna de cejas', 'Laminado de cejas', 'Microblading',
            'Lifting de pestanas', 'Extensiones pelo a pelo',
            'Extensiones volumen', 'Tinte de pestanas',
        ],
        'other' => [
            'Tecnica manual', 'Tecnica con aparatologia',
            'Tratamiento especializado',
        ],
    ];

    const BODY_ZONES_FRONT = [
        ['id' => 'cabeza', 'label' => 'Cabeza'],
        ['id' => 'cuello', 'label' => 'Cuello'],
        ['id' => 'hombro-izq', 'label' => 'Hombro izquierdo'],
        ['id' => 'hombro-der', 'label' => 'Hombro derecho'],
        ['id' => 'pecho', 'label' => 'Pecho'],
        ['id' => 'abdomen', 'label' => 'Abdomen'],
        ['id' => 'brazo-izq', 'label' => 'Brazo izquierdo'],
        ['id' => 'brazo-der', 'label' => 'Brazo derecho'],
        ['id' => 'antebrazo-izq', 'label' => 'Antebrazo izquierdo'],
        ['id' => 'antebrazo-der', 'label' => 'Antebrazo derecho'],
        ['id' => 'mano-izq', 'label' => 'Mano izquierda'],
        ['id' => 'mano-der', 'label' => 'Mano derecha'],
        ['id' => 'caderas', 'label' => 'Caderas'],
        ['id' => 'muslo-izq', 'label' => 'Muslo izquierdo'],
        ['id' => 'muslo-der', 'label' => 'Muslo derecho'],
        ['id' => 'rodilla-izq', 'label' => 'Rodilla izquierda'],
        ['id' => 'rodilla-der', 'label' => 'Rodilla derecha'],
        ['id' => 'pantorrilla-izq', 'label' => 'Pantorrilla izquierda'],
        ['id' => 'pantorrilla-der', 'label' => 'Pantorrilla derecha'],
        ['id' => 'pie-izq', 'label' => 'Pie izquierdo'],
        ['id' => 'pie-der', 'label' => 'Pie derecho'],
    ];

    const BODY_ZONES_BACK = [
        ['id' => 'b-cuello', 'label' => 'Cuello posterior'],
        ['id' => 'b-hombro-izq', 'label' => 'Hombro izquierdo (dorsal)'],
        ['id' => 'b-hombro-der', 'label' => 'Hombro derecho (dorsal)'],
        ['id' => 'b-espalda-alta', 'label' => 'Espalda alta'],
        ['id' => 'b-lumbar', 'label' => 'Zona lumbar'],
        ['id' => 'b-gluteos', 'label' => 'Glúteos'],
        ['id' => 'b-brazo-izq', 'label' => 'Brazo izquierdo (dorsal)'],
        ['id' => 'b-brazo-der', 'label' => 'Brazo derecho (dorsal)'],
        ['id' => 'b-muslo-izq', 'label' => 'Muslo posterior izquierdo'],
        ['id' => 'b-muslo-der', 'label' => 'Muslo posterior derecho'],
        ['id' => 'b-corva-izq', 'label' => 'Corva izquierda'],
        ['id' => 'b-corva-der', 'label' => 'Corva derecha'],
        ['id' => 'b-gemelo-izq', 'label' => 'Gemelo izquierdo'],
        ['id' => 'b-gemelo-der', 'label' => 'Gemelo derecho'],
        ['id' => 'b-talon-izq', 'label' => 'Talón izquierdo'],
        ['id' => 'b-talon-der', 'label' => 'Talón derecho'],
    ];
}
