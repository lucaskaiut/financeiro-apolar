<?php

namespace App\Modules\Account\Services;

use App\Modules\Account\Models\AccountAllocation;
use App\Modules\Account\Models\FinancialAccount;
use Illuminate\Support\Collection;
use InvalidArgumentException;

class AllocationService
{
    /**
     * @param  list<array{cost_center_id?: ?string, company_id?: ?string, category_id?: ?string, subcategory_id?: ?string, value: numeric, percentage?: ?numeric}>  $allocations
     */
    public function sync(FinancialAccount $account, array $allocations): void
    {
        if ($allocations === []) {
            $account->allocations()->delete();
            $account->update(['allocation_mode' => 'single']);

            return;
        }

        $total = round((float) $account->value, 2);
        $distributed = round(collect($allocations)->sum(fn (array $item) => (float) $item['value']), 2);

        if (abs($distributed - $total) > 0.009) {
            throw new InvalidArgumentException(
                sprintf('A soma do rateio (R$ %s) deve ser igual ao valor total (R$ %s).', number_format($distributed, 2, ',', '.'), number_format($total, 2, ',', '.')),
            );
        }

        $account->allocations()->delete();

        foreach ($allocations as $item) {
            AccountAllocation::query()->create([
                'tenant_id' => $account->tenant_id,
                'account_id' => $account->getKey(),
                'cost_center_id' => $item['cost_center_id'] ?? null,
                'company_id' => $item['company_id'] ?? null,
                'category_id' => $item['category_id'] ?? null,
                'subcategory_id' => $item['subcategory_id'] ?? null,
                'value' => round((float) $item['value'], 2),
                'percentage' => isset($item['percentage']) ? round((float) $item['percentage'], 4) : null,
            ]);
        }

        $account->update(['allocation_mode' => 'split']);
    }

    /**
     * @param  list<array{cost_center_id?: ?string, company_id?: ?string, category_id?: ?string, subcategory_id?: ?string, value?: numeric, percentage?: numeric}>  $allocations
     * @return list<array{cost_center_id?: ?string, company_id?: ?string, category_id?: ?string, subcategory_id?: ?string, value: float, percentage?: ?float}>
     */
    public function normalize(float $total, array $allocations): array
    {
        if ($allocations === []) {
            return [];
        }

        $hasPercentages = collect($allocations)->every(fn (array $item) => isset($item['percentage']) && ! isset($item['value']));
        $normalized = [];

        if ($hasPercentages) {
            $percentTotal = round(collect($allocations)->sum(fn (array $item) => (float) ($item['percentage'] ?? 0)), 4);

            if (abs($percentTotal - 100) > 0.01) {
                throw new InvalidArgumentException('A soma dos percentuais deve ser 100%.');
            }

            $accumulated = 0.0;

            foreach ($allocations as $index => $item) {
                $isLast = $index === count($allocations) - 1;
                $value = $isLast
                    ? round($total - $accumulated, 2)
                    : round($total * ((float) $item['percentage'] / 100), 2);

                $accumulated = round($accumulated + $value, 2);

                $normalized[] = [
                    ...$item,
                    'value' => $value,
                    'percentage' => round((float) $item['percentage'], 4),
                ];
            }

            return $normalized;
        }

        foreach ($allocations as $item) {
            $normalized[] = [
                ...$item,
                'value' => round((float) ($item['value'] ?? 0), 2),
                'percentage' => isset($item['percentage']) ? round((float) $item['percentage'], 4) : null,
            ];
        }

        return $normalized;
    }

    /**
     * @return Collection<int, AccountAllocation>
     */
    public function forReports(?string $companyId = null, ?string $costCenterId = null): Collection
    {
        return AccountAllocation::query()
            ->with(['account', 'costCenter', 'company', 'category'])
            ->when($companyId, fn ($q) => $q->where('company_id', $companyId))
            ->when($costCenterId, fn ($q) => $q->where('cost_center_id', $costCenterId))
            ->get();
    }
}
