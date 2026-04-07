<?php

namespace App\Constants;

class ServiceTypeFields
{
    const FIELDS = [
        'hair' => [
            'body_map' => false,
            'initial_condition' => true,
            'technique' => true,
            'temperature' => true,
            'duration' => true,
            'products' => true,
            'result' => true,
            'next_visit' => true,
            'internal_notes' => true,
        ],
        'spa' => [
            'body_map' => true,
            'initial_condition' => true,
            'technique' => true,
            'temperature' => false,
            'duration' => true,
            'products' => true,
            'result' => true,
            'next_visit' => true,
            'internal_notes' => true,
        ],
        'facial' => [
            'body_map' => false,
            'initial_condition' => true,
            'technique' => true,
            'temperature' => true,
            'duration' => true,
            'products' => true,
            'result' => true,
            'next_visit' => true,
            'internal_notes' => true,
        ],
        'nails' => [
            'body_map' => false,
            'initial_condition' => true,
            'technique' => true,
            'temperature' => false,
            'duration' => false,
            'products' => true,
            'result' => true,
            'next_visit' => true,
            'internal_notes' => true,
        ],
        'brows' => [
            'body_map' => false,
            'initial_condition' => true,
            'technique' => true,
            'temperature' => false,
            'duration' => false,
            'products' => true,
            'result' => true,
            'next_visit' => true,
            'internal_notes' => true,
        ],
        'other' => [
            'body_map' => false,
            'initial_condition' => true,
            'technique' => true,
            'temperature' => false,
            'duration' => false,
            'products' => true,
            'result' => true,
            'next_visit' => true,
            'internal_notes' => true,
        ],
    ];

    public static function forType(string $type): array
    {
        return self::FIELDS[$type] ?? self::FIELDS['other'];
    }
}
