<?php

namespace App\Modules\Account\Enums;

enum AccountStatus: string
{
    case Open = 'open';
    case Partial = 'partial';
    case Settled = 'settled';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Open => 'Aberto',
            self::Partial => 'Parcial',
            self::Settled => 'Liquidado',
            self::Cancelled => 'Cancelado',
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
