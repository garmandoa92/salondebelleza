<?php

namespace App\Enums;

enum AdvanceType: string
{
    case Advance = 'advance';
    case Payment = 'payment';
    case Refund = 'refund';
}
