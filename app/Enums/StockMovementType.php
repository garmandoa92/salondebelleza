<?php

namespace App\Enums;

enum StockMovementType: string
{
    case Purchase = 'purchase';
    case Consumption = 'consumption';
    case Adjustment = 'adjustment';
    case Sale = 'sale';
    case Initial = 'initial';
}
