<?php

namespace App\Modules\Shared\Casts;

use App\Modules\Shared\Support\DateOnly;
use Carbon\Carbon;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * Cast de data de calendário (Y-m-d) que não desloca o dia por fuso horário.
 *
 * @implements CastsAttributes<Carbon|null, string|null>
 */
final class DateOnlyCast implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): ?Carbon
    {
        if ($value === null || $value === '') {
            return null;
        }

        return DateOnly::parse($value);
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        return DateOnly::normalize($value);
    }
}
