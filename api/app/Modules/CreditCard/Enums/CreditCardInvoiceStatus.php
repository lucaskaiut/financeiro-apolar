<?php

namespace App\Modules\CreditCard\Enums;

enum CreditCardInvoiceStatus: string
{
    case Open = 'open';
    case Closed = 'closed';
    case Paid = 'paid';

    public function label(): string
    {
        return match ($this) {
            self::Open => 'Aberta',
            self::Closed => 'Fechada',
            self::Paid => 'Paga',
        };
    }
}
