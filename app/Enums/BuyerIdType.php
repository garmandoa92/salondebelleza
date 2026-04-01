<?php

namespace App\Enums;

enum BuyerIdType: string
{
    case Ruc = 'RUC';
    case Cedula = 'cedula';
    case Passport = 'passport';
    case FinalConsumer = 'final_consumer';
}
