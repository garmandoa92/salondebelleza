<?php

namespace App\Enums;

enum InvoiceType: string
{
    case Invoice = 'invoice';
    case CreditNote = 'credit_note';
    case DebitNote = 'debit_note';
    case SaleNote = 'sale_note';
    case PurchaseLiquidation = 'purchase_liquidation';
}
