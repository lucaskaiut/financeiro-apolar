<?php

namespace App\Modules\Shared\Support;

use Carbon\Carbon;
use InvalidArgumentException;

final class DateOnly
{
    /**
     * Normaliza um valor de data de calendário para Y-m-d, sem deslocar o dia por fuso.
     *
     * Aceita "Y-m-d" ou qualquer string que comece com Y-m-d (ex.: ISO com horário/Z).
     * O prefixo da data é preservado — nunca converte via timezone.
     */
    public static function normalize(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof Carbon) {
            return $value->format('Y-m-d');
        }

        $raw = trim((string) $value);

        if (preg_match('/^(\d{4}-\d{2}-\d{2})/', $raw, $matches) === 1) {
            return $matches[1];
        }

        throw new InvalidArgumentException("Data inválida: {$raw}");
    }

    /**
     * Carbon no início do dia em Y-m-d, sem interpretação de fuso no parse.
     */
    public static function parse(mixed $value): Carbon
    {
        $normalized = self::normalize($value);

        if ($normalized === null) {
            throw new InvalidArgumentException('Data obrigatória.');
        }

        return Carbon::createFromFormat('Y-m-d', $normalized)->startOfDay();
    }
}
