<?php

namespace App\Modules\Account\Support;

use App\Modules\Account\Enums\AllocationMode;
use App\Modules\Account\Models\FinancialAccount;

final class ClassificationSlices
{
    /**
     * Relações necessárias para expandir classificação (cabeçalho ou rateio).
     *
     * @return list<string>
     */
    public static function withAccount(): array
    {
        return [
            'costCenter:id,uuid,name',
            'category:id,uuid,name,color,type',
            'subcategory:id,uuid,name',
            'allocations.costCenter:id,uuid,name',
            'allocations.category:id,uuid,name,color,type',
            'allocations.subcategory:id,uuid,name',
        ];
    }

    /**
     * @return list<array{
     *     allocation_id: ?string,
     *     cost_center_id: ?string,
     *     cost_center_name: ?string,
     *     category_id: ?string,
     *     category_name: ?string,
     *     category_type: ?string,
     *     subcategory_id: ?string,
     *     subcategory_name: ?string,
     *     value: float,
     *     percentage: float
     * }>
     */
    public static function forAmount(FinancialAccount $account, float $amount): array
    {
        $amount = round($amount, 2);

        if ($amount == 0.0) {
            return [];
        }

        $allocations = $account->relationLoaded('allocations')
            ? $account->allocations
            : $account->allocations()->with(['costCenter:id,uuid,name', 'category:id,uuid,name,color,type', 'subcategory:id,uuid,name'])->get();

        $isSplit = $account->allocation_mode === AllocationMode::Split && $allocations->isNotEmpty();

        if (! $isSplit) {
            return [self::headerSlice($account, $amount, 100.0)];
        }

        $base = round((float) $account->value, 2);

        if ($base <= 0) {
            return [self::headerSlice($account, $amount, 100.0)];
        }

        $slices = [];
        $allocated = 0.0;
        $items = $allocations->values();
        $lastIndex = $items->count() - 1;

        foreach ($items as $index => $allocation) {
            $isLast = $index === $lastIndex;
            $value = $isLast
                ? round($amount - $allocated, 2)
                : round(((float) $allocation->value / $base) * $amount, 2);

            $allocated = round($allocated + $value, 2);
            $percentage = $amount != 0.0 ? round(($value / $amount) * 100, 4) : 0.0;

            $slices[] = [
                'allocation_id' => $allocation->uuid,
                'cost_center_id' => $allocation->cost_center_id,
                'cost_center_name' => $allocation->costCenter?->name,
                'category_id' => $allocation->category_id,
                'category_name' => $allocation->category?->name,
                'category_type' => $allocation->category?->type?->value,
                'subcategory_id' => $allocation->subcategory_id,
                'subcategory_name' => $allocation->subcategory?->name,
                'value' => $value,
                'percentage' => $percentage,
            ];
        }

        return $slices;
    }

    public static function amountMatching(
        FinancialAccount $account,
        float $amount,
        ?string $costCenterId = null,
        ?string $categoryId = null,
    ): float {
        if (($costCenterId === null || $costCenterId === '') && ($categoryId === null || $categoryId === '')) {
            return round($amount, 2);
        }

        $total = 0.0;

        foreach (self::forAmount($account, $amount) as $slice) {
            if ($costCenterId !== null && $costCenterId !== '' && $slice['cost_center_id'] !== $costCenterId) {
                continue;
            }

            if ($categoryId !== null && $categoryId !== '' && $slice['category_id'] !== $categoryId) {
                continue;
            }

            $total += $slice['value'];
        }

        return round($total, 2);
    }

    /**
     * @return array{
     *     allocation_id: ?string,
     *     cost_center_id: ?string,
     *     cost_center_name: ?string,
     *     category_id: ?string,
     *     category_name: ?string,
     *     category_type: ?string,
     *     subcategory_id: ?string,
     *     subcategory_name: ?string,
     *     value: float,
     *     percentage: float
     * }
     */
    private static function headerSlice(FinancialAccount $account, float $amount, float $percentage): array
    {
        return [
            'allocation_id' => null,
            'cost_center_id' => $account->cost_center_id,
            'cost_center_name' => $account->costCenter?->name,
            'category_id' => $account->category_id,
            'category_name' => $account->category?->name,
            'category_type' => $account->category?->type?->value,
            'subcategory_id' => $account->subcategory_id,
            'subcategory_name' => $account->subcategory?->name,
            'value' => $amount,
            'percentage' => $percentage,
        ];
    }
}
