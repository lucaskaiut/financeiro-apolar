<?php

namespace App\Modules\Account\Enums;

enum AllocationMode: string
{
    case Single = 'single';
    case Split = 'split';

    public function label(): string
    {
        return match ($this) {
            self::Single => 'Único',
            self::Split => 'Rateado',
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
