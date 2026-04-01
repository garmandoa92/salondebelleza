<?php

namespace App\Enums;

enum AppointmentSource: string
{
    case Manual = 'manual';
    case OnlineBooking = 'online_booking';
    case Whatsapp = 'whatsapp';
    case Phone = 'phone';
}
