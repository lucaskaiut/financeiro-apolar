<?php

namespace App\Modules\Account\Http\Requests;

use App\Modules\Account\Enums\AccountType;
use App\Modules\Category\Models\Category;
use App\Modules\Shared\Support\DateOnly;
use App\Modules\Tenant\Support\Facades\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $tenantId = TenantContext::tenantId();
        $hasCreditCard = filled($this->input('credit_card_id'));

        return [
            'type' => ['required', 'string', Rule::in(AccountType::values())],
            'description' => ['required', 'string', 'max:255'],
            'counterparty' => ['nullable', 'string', 'max:255'],
            'bank_account_id' => [
                'nullable',
                'string',
                Rule::requiredIf(! $hasCreditCard),
                Rule::exists('bank_accounts', 'uuid')->where(fn ($q) => $q->where('tenant_id', $tenantId)),
            ],
            'credit_card_id' => [
                'nullable',
                'string',
                Rule::exists('credit_cards', 'uuid')->where(fn ($q) => $q->where('tenant_id', $tenantId)),
            ],
            'company_id' => [
                'nullable',
                'string',
                Rule::exists('companies', 'uuid')->where(fn ($q) => $q->where('tenant_id', $tenantId)),
            ],
            'cost_center_id' => [
                'nullable',
                'string',
                Rule::exists('cost_centers', 'uuid')->where(fn ($q) => $q->where('tenant_id', $tenantId)),
            ],
            'category_id' => [
                Rule::requiredIf(fn () => ! AccountAllocationValidation::hasAllocations($this->input('allocations'))),
                'nullable',
                'string',
                Rule::exists('categories', 'uuid')->where(fn ($q) => $q->where('tenant_id', $tenantId)),
            ],
            'subcategory_id' => [
                'nullable',
                'string',
                Rule::exists('categories', 'uuid')->where(fn ($q) => $q
                    ->where('tenant_id', $tenantId)
                    ->whereNotNull('parent_id')),
            ],
            'value' => ['required', 'numeric', 'gt:0'],
            'due_date' => [$hasCreditCard ? 'nullable' : 'required', 'date'],
            'purchase_date' => [$hasCreditCard ? 'required' : 'nullable', 'date'],
            'expected_date' => ['nullable', 'date'],
            'observation' => ['nullable', 'string'],
            'installments' => ['nullable', 'array:quantity,interval,items'],
            'installments.quantity' => ['required_with:installments', 'integer', 'min:1', 'max:120'],
            'installments.interval' => ['nullable', 'string', Rule::in(['daily', 'weekly', 'monthly'])],
            'installments.items' => ['nullable', 'array', 'min:1', 'max:120'],
            'installments.items.*.value' => ['required', 'numeric', 'gt:0'],
            'installments.items.*.due_date' => ['required', 'date'],
            ...AccountAllocationValidation::rules($tenantId),
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            AccountAllocationValidation::after(
                $validator,
                fn () => (float) $this->input('value'),
            );

            $this->validateInstallmentItems($validator);
        });
    }

    protected function prepareForValidation(): void
    {
        if ($this->filled('value')) {
            $this->merge(['value' => (float) $this->input('value')]);
        }

        if ($this->input('credit_card_id') === '') {
            $this->merge(['credit_card_id' => null]);
        }

        if ($this->input('bank_account_id') === '') {
            $this->merge(['bank_account_id' => null]);
        }

        if (filled($this->input('credit_card_id'))) {
            $this->merge([
                'type' => AccountType::Payable->value,
                'bank_account_id' => null,
            ]);
        }

        $this->mergeDateOnlyFields(['due_date', 'purchase_date', 'expected_date']);

        $this->normalizeInstallmentItems();
        $this->fillCategoryFromSubcategory();
        $this->merge(AccountAllocationValidation::fillCategoriesFromSubcategories($this->all()));
    }

    private function normalizeInstallmentItems(): void
    {
        $installments = $this->input('installments');

        if (! is_array($installments) || ! isset($installments['items']) || ! is_array($installments['items'])) {
            return;
        }

        foreach ($installments['items'] as $index => $item) {
            if (array_key_exists('value', $item)) {
                $installments['items'][$index]['value'] = (float) $item['value'];
            }

            if (! empty($item['due_date'])) {
                try {
                    $installments['items'][$index]['due_date'] = DateOnly::normalize($item['due_date']);
                } catch (\InvalidArgumentException) {
                    // Mantém o valor original para a validação `date` falhar com mensagem clara.
                }
            }
        }

        $this->merge(['installments' => $installments]);
    }

    private function validateInstallmentItems(Validator $validator): void
    {
        $items = $this->input('installments.items');

        if (! is_array($items) || $items === []) {
            return;
        }

        $sum = round(array_sum(array_map(
            fn ($item) => (float) ($item['value'] ?? 0),
            $items,
        )), 2);

        $total = round((float) $this->input('value'), 2);

        if (abs($sum - $total) >= 0.01) {
            $validator->errors()->add(
                'installments.items',
                'A soma dos valores das parcelas deve ser igual ao valor do lançamento.',
            );
        }
    }

    /**
     * @param  list<string>  $fields
     */
    private function mergeDateOnlyFields(array $fields): void
    {
        $merged = [];

        foreach ($fields as $field) {
            if (! $this->exists($field)) {
                continue;
            }

            $value = $this->input($field);

            if ($value === null || $value === '') {
                $merged[$field] = null;
                continue;
            }

            try {
                $merged[$field] = DateOnly::normalize($value);
            } catch (\InvalidArgumentException) {
                // Mantém o valor original para a validação `date` falhar com mensagem clara.
            }
        }

        if ($merged !== []) {
            $this->merge($merged);
        }
    }

    private function fillCategoryFromSubcategory(): void
    {
        if (! $this->filled('subcategory_id')) {
            return;
        }

        $subcategory = Category::query()
            ->where('uuid', $this->input('subcategory_id'))
            ->whereNotNull('parent_id')
            ->first();

        $parent = $subcategory?->parent;

        if ($parent !== null) {
            $this->merge(['category_id' => $parent->uuid]);
        }
    }
}
