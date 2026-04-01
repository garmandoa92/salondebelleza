<?php

namespace App\Enums;

enum ClientSource: string
{
    case WalkIn = 'walk_in';
    case Referral = 'referral';
    case Instagram = 'instagram';
    case Whatsapp = 'whatsapp';
    case Website = 'website';
    case Other = 'other';
}
