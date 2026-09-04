<?php

namespace App\Modules\BankAccount\Enums;

enum BankAccountType: string
{
    case Checking = 'checking';
    case Savings = 'savings';
    case Investment = 'investment';
    case CreditCard = 'credit_card';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Checking => 'Conta corrente',
            self::Savings => 'Conta poupança',
            self::Investment => 'Investimento',
            self::CreditCard => 'Cartão de crédito',
            self::Other => 'Outro',
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
