<?php

namespace App\Enums;

enum SriStatus: string
{
    case Draft = 'draft';
    case Signed = 'signed';
    case Sent = 'sent';
    case Authorized = 'authorized';
    case Rejected = 'rejected';
    case Cancelled = 'cancelled';
}
