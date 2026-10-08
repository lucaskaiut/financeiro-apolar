<?php

namespace App\Modules\CreditCard\Http\Requests;

use App\Modules\Shared\Support\DateOnly;
use App\Modules\Tenant\Support\Facades\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreCreditCardPurchaseRequest extends FormRequest
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

        return [
            'description' => ['required', 'string', 'max:255'],
            'counterparty' => ['nullable', 'string', 'max:255'],
            'company_id' => ['nullable', 'string', Rule::exists('companies', 'uuid')->where(fn ($q) => $q->where('tenant_id', $tenantId))],
            'cost_center_id' => ['nullable', 'string', Rule::exists('cost_centers', 'uuid')->where(fn ($q) => $q->where('tenant_id', $tenantId))],
            'category_id' => ['required', 'string', Rule::exists('categories', 'uuid')->where(fn ($q) => $q->where('tenant_id', $tenantId))],
            'subcategory_id' => ['nullable', 'string', Rule::exists('categories', 'uuid')->where(fn ($q) => $q->where('tenant_id', $tenantId))],
            'value' => ['required', 'numeric', 'gt:0'],
            'purchase_date' => ['required', 'date'],
            'observation' => ['nullable', 'string'],
            'installments' => ['nullable', 'array:quantity,items'],
            'installments.quantity' => [
                'nullable',
                Rule::requiredIf(fn () => is_array($this->input('installments')) && blank($this->input('installments.items'))),
                'integer',
                'min:1',
                'max:120',
            ],
            'installments.items' => ['nullable', 'array', 'min:1', 'max:120'],
            'installments.items.*.value' => ['required', 'numeric', 'gt:0'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
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
                    'A soma dos valores das parcelas deve ser igual ao valor da compra.',
                );
            }
        });
    }

    protected function prepareForValidation(): void
    {
        if ($this->filled('value')) {
            $this->merge(['value' => (float) $this->input('value')]);
        }

        if ($this->filled('purchase_date')) {
            try {
                $this->merge(['purchase_date' => DateOnly::normalize($this->input('purchase_date'))]);
            } catch (\InvalidArgumentException) {
                // Mantém o valor original para a validação `date` falhar.
            }
        }

        $this->normalizeInstallmentItems();
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
        }

        $this->merge(['installments' => $installments]);
    }
}
