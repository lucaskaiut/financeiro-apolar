<?php

namespace App\Modules\Installment\Http\Requests;

use App\Modules\Account\Enums\AccountType;
use App\Modules\Category\Models\Category;
use App\Modules\Shared\Support\DateOnly;
use App\Modules\Tenant\Support\Facades\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateInstallmentRequest extends FormRequest
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
            'type' => ['sometimes', 'required', 'string', Rule::in(AccountType::values())],
            'description' => ['sometimes', 'required', 'string', 'max:255'],
            'counterparty' => ['nullable', 'string', 'max:255'],
            'bank_account_id' => [
                'sometimes',
                'required',
                'string',
                Rule::exists('bank_accounts', 'uuid')->where(fn ($q) => $q->where('tenant_id', $tenantId)),
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
            'value' => ['sometimes', 'required', 'numeric', 'gt:0'],
            'expected_date' => ['nullable', 'date'],
            'observation' => ['nullable', 'string'],
            'scope' => ['sometimes', 'string', Rule::in(['all', 'future'])],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->filled('value')) {
            $this->merge(['value' => (float) $this->input('value')]);
        }

        $this->mergeDateOnlyFields(['expected_date']);
        $this->fillCategoryFromSubcategory();
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
