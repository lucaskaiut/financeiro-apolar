<?php

namespace App\Modules\Account\Http\Requests;

use App\Modules\Category\Models\Category;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

final class AccountAllocationValidation
{
    /**
     * @return array<string, mixed>
     */
    public static function rules(int|string|null $tenantId): array
    {
        $tenantConstraint = fn ($q) => $q->where('tenant_id', $tenantId);

        return [
            'allocations' => ['nullable', 'array'],
            'allocations.*.category_id' => [
                'nullable',
                'string',
                Rule::exists('categories', 'uuid')->where($tenantConstraint),
            ],
            'allocations.*.subcategory_id' => [
                'nullable',
                'string',
                Rule::exists('categories', 'uuid')->where(fn ($q) => $q
                    ->where('tenant_id', $tenantId)
                    ->whereNotNull('parent_id')),
            ],
            'allocations.*.cost_center_id' => [
                'nullable',
                'string',
                Rule::exists('cost_centers', 'uuid')->where($tenantConstraint),
            ],
            'allocations.*.company_id' => [
                'nullable',
                'string',
                Rule::exists('companies', 'uuid')->where($tenantConstraint),
            ],
            'allocations.*.value' => ['nullable', 'numeric', 'gt:0'],
        ];
    }

    public static function hasAllocations(mixed $allocations): bool
    {
        return is_array($allocations) && $allocations !== [];
    }

    /**
     * @param  callable(): float  $valueResolver
     */
    public static function after(Validator $validator, callable $valueResolver): void
    {
        $allocations = $validator->getData()['allocations'] ?? null;

        if (! self::hasAllocations($allocations)) {
            return;
        }

        $lines = [];

        foreach ($allocations as $index => $line) {
            if (! is_array($line)) {
                continue;
            }

            $value = round((float) ($line['value'] ?? 0), 2);
            $categoryId = $line['category_id'] ?? null;

            if ($value <= 0 && blank($categoryId)) {
                continue;
            }

            if (blank($categoryId)) {
                $validator->errors()->add("allocations.{$index}.category_id", 'Selecione a categoria da linha de rateio.');
            }

            if ($value <= 0) {
                $validator->errors()->add("allocations.{$index}.value", 'Informe um valor maior que zero.');
            }

            $lines[] = $value;
        }

        if (count($lines) < 2) {
            $validator->errors()->add('allocations', 'Informe pelo menos duas linhas de rateio.');

            return;
        }

        $sum = round(array_sum($lines), 2);
        $value = round((float) $valueResolver(), 2);

        if (abs($sum - $value) >= 0.01) {
            $validator->errors()->add('allocations', 'A soma do rateio deve ser igual ao valor do lançamento.');
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public static function fillCategoriesFromSubcategories(array $payload): array
    {
        $allocations = $payload['allocations'] ?? null;

        if (! is_array($allocations)) {
            return $payload;
        }

        foreach ($allocations as $index => $line) {
            if (! is_array($line)) {
                continue;
            }

            foreach (['category_id', 'subcategory_id', 'cost_center_id', 'company_id'] as $field) {
                if (array_key_exists($field, $line) && $line[$field] === '') {
                    $allocations[$index][$field] = null;
                }
            }
        }

        $payload['allocations'] = $allocations;

        $subcategoryIds = [];

        foreach ($allocations as $line) {
            if (is_array($line) && filled($line['subcategory_id'] ?? null)) {
                $subcategoryIds[] = $line['subcategory_id'];
            }
        }

        if ($subcategoryIds === []) {
            return $payload;
        }

        $parents = Category::query()
            ->with('parent')
            ->whereIn('uuid', array_unique($subcategoryIds))
            ->whereNotNull('parent_id')
            ->get()
            ->keyBy('uuid');

        foreach ($allocations as $index => $line) {
            if (! is_array($line) || blank($line['subcategory_id'] ?? null)) {
                continue;
            }

            $parent = $parents->get($line['subcategory_id'])?->parent;

            if ($parent !== null) {
                $allocations[$index]['category_id'] = $parent->uuid;
            }
        }

        $payload['allocations'] = $allocations;

        return $payload;
    }
}
