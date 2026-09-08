<?php

namespace App\Modules\Account\Services;

use App\Modules\Account\Enums\AllocationMode;
use App\Modules\Account\Models\FinancialAccount;

class AllocationService
{
    /**
     * @param  list<array<string, mixed>>|null  $allocations
     */
    public function sync(FinancialAccount $account, ?array $allocations): void
    {
        $lines = $this->normalize($allocations, (float) $account->value);

        $account->allocations()->delete();

        if ($lines === []) {
            if ($account->allocation_mode !== AllocationMode::Single) {
                $account->allocation_mode = AllocationMode::Single;
                $account->save();
            }

            return;
        }

        foreach ($lines as $line) {
            $account->allocations()->create($line);
        }

        $account->allocation_mode = AllocationMode::Split;
        $account->category_id = null;
        $account->subcategory_id = null;
        $account->cost_center_id = null;
        $account->save();
    }

    /**
     * Distribui as linhas de rateio entre parcelas, preservando o total de cada linha.
     *
     * @param  list<array<string, mixed>>  $allocations
     * @param  list<float>  $targets
     * @return list<list<array<string, mixed>>>
     */
    public function distributeAcross(array $allocations, array $targets): array
    {
        $count = count($targets);

        if ($count === 0) {
            return [];
        }

        $result = array_fill(0, $count, []);

        foreach ($allocations as $line) {
            $parts = $this->splitValue((float) ($line['value'] ?? 0), $targets);

            foreach ($parts as $index => $value) {
                $target = $targets[$index];
                $result[$index][] = [
                    ...$line,
                    'value' => $value,
                    'percentage' => $target > 0 ? round(($value / $target) * 100, 4) : 0.0,
                ];
            }
        }

        return $result;
    }

    /**
     * @param  list<array<string, mixed>>|null  $allocations
     * @return list<array<string, mixed>>
     */
    public function normalize(?array $allocations, float $accountValue): array
    {
        if (! is_array($allocations)) {
            return [];
        }

        $filtered = [];

        foreach ($allocations as $line) {
            if (! is_array($line)) {
                continue;
            }

            $value = round((float) ($line['value'] ?? 0), 2);
            $categoryId = $line['category_id'] ?? null;

            if ($value <= 0 || blank($categoryId)) {
                continue;
            }

            $filtered[] = [
                'cost_center_id' => filled($line['cost_center_id'] ?? null) ? $line['cost_center_id'] : null,
                'company_id' => filled($line['company_id'] ?? null) ? $line['company_id'] : null,
                'category_id' => $categoryId,
                'subcategory_id' => filled($line['subcategory_id'] ?? null) ? $line['subcategory_id'] : null,
                'value' => $value,
            ];
        }

        if (count($filtered) < 2) {
            return [];
        }

        $accountValue = round($accountValue, 2);
        $accumulated = 0.0;
        $lastIndex = count($filtered) - 1;
        $normalized = [];

        foreach ($filtered as $index => $line) {
            $value = $index === $lastIndex
                ? round($accountValue - $accumulated, 2)
                : $line['value'];

            $accumulated = round($accumulated + $value, 2);

            $normalized[] = [
                ...$line,
                'value' => $value,
                'percentage' => $accountValue > 0 ? round(($value / $accountValue) * 100, 4) : 0.0,
            ];
        }

        return $normalized;
    }

    /**
     * @param  list<float>  $targets
     * @return list<float>
     */
    private function splitValue(float $total, array $targets): array
    {
        $count = count($targets);
        $targetSum = round(array_sum($targets), 2);

        if ($count === 0 || $targetSum <= 0) {
            return [];
        }

        $parts = [];
        $accumulated = 0.0;
        $lastIndex = $count - 1;

        foreach (array_values($targets) as $index => $target) {
            $value = $index === $lastIndex
                ? round($total - $accumulated, 2)
                : round($total * ((float) $target / $targetSum), 2);

            $parts[] = $value;
            $accumulated = round($accumulated + $value, 2);
        }

        return $parts;
    }
}
