<?php

namespace App\Enums;

enum AdvanceStatus: string
{
    case Pending = 'pending';
    case Applied = 'applied';
    case Refunded = 'refunded';
}
