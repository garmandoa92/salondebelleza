<?php

namespace App\Enums;

enum PaymentMethod: string
{
    case Cash = 'cash';
    case Transfer = 'transfer';
    case CardDebit = 'card_debit';
    case CardCredit = 'card_credit';
    case Other = 'other';
}
