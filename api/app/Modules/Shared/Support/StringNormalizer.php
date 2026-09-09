<?php

namespace App\Modules\Shared\Support;

use Normalizer;

final class StringNormalizer
{
    public static function trim(string $value): string
    {
        return trim($value);
    }

    /**
     * Normaliza texto para comparação: trim, lowercase e remoção de acentos.
     */
    public static function sanitize(string $value): string
    {
        return self::removeAccents(mb_strtolower(trim($value)));
    }

    /**
     * Remove acentos usando a extensão intl (funciona em Alpine/musl,
     * onde o iconv //TRANSLIT não translitera corretamente).
     */
    private static function removeAccents(string $value): string
    {
        $decomposed = Normalizer::normalize($value, Normalizer::FORM_D);

        if ($decomposed === false) {
            return $value;
        }

        return preg_replace('/[\x{0300}-\x{036F}]/u', '', $decomposed) ?? $value;
    }
}
