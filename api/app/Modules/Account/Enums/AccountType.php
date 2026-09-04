<?php

namespace App\Modules\Account\Enums;

enum AccountType: string
{
    case Payable = 'payable';
    case Receivable = 'receivable';

    public function label(): string
    {
        return match ($this) {
            self::Payable => 'Conta a pagar',
            self::Receivable => 'Conta a receber',
        };
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
