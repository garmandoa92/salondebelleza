<?php

namespace App\Enums;

enum CancelledBy: string
{
    case Client = 'client';
    case Staff = 'staff';
}
