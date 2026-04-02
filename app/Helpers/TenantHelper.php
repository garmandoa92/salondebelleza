<?php

if (! function_exists('tenantIva')) {
    function tenantIva(): float
    {
        return (float) (tenant()?->settings['iva_rate'] ?? 15);
    }
}

if (! function_exists('ivaCode')) {
    function ivaCode(float $rate): string
    {
        return match ((int) $rate) {
            15 => '4',
            12 => '2',
            0 => '0',
            default => '4',
        };
    }
}
