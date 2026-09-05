<?php

namespace App\Modules\Account\Http\Requests;

use App\Modules\Account\Enums\AccountType;
use App\Modules\Account\Models\FinancialAccount;
use App\Modules\Category\Models\Category;
use App\Modules\Shared\Support\DateOnly;
use App\Modules\Tenant\Support\Facades\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAccountRequest extends FormRequest
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
        $account = $this->route('account');
        $isCardPurchase = $account instanceof FinancialAccount && (bool) $account->is_card_purchase;

        return [
            'type' => ['sometimes', 'required', 'string', Rule::in(AccountType::values())],
            'description' => ['sometimes', 'required', 'string', 'max:255'],
            'counterparty' => ['nullable', 'string', 'max:255'],
            'bank_account_id' => [
                'sometimes',
                $isCardPurchase ? 'nullable' : 'required',
                'string',
                Rule::exists('bank_accounts', 'uuid')->where(fn ($q) => $q->where('tenant_id', TenantContext::tenantId())),
            ],
            'company_id' => [
                'nullable',
                'string',
                Rule::exists('companies', 'uuid')->where(fn ($q) => $q->where('tenant_id', TenantContext::tenantId())),
            ],
            'cost_center_id' => [
                'nullable',
                'string',
                Rule::exists('cost_centers', 'uuid')->where(fn ($q) => $q->where('tenant_id', TenantContext::tenantId())),
            ],
            'category_id' => [
                'sometimes',
                'required',
                'string',
                Rule::exists('categories', 'uuid')->where(fn ($q) => $q->where('tenant_id', TenantContext::tenantId())),
            ],
            'subcategory_id' => [
                'nullable',
                'string',
                Rule::exists('categories', 'uuid')->where(fn ($q) => $q
                    ->where('tenant_id', TenantContext::tenantId())
                    ->whereNotNull('parent_id')),
            ],
            'value' => ['sometimes', 'required', 'numeric', 'gt:0'],
            'due_date' => ['sometimes', $isCardPurchase ? 'nullable' : 'required', 'date'],
            'purchase_date' => ['nullable', 'date'],
            'expected_date' => ['nullable', 'date'],
            'paid_date' => ['nullable', 'date'],
            'observation' => ['nullable', 'string'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->filled('value')) {
            $this->merge(['value' => (float) $this->input('value')]);
        }

        if ($this->exists('paid_date') && $this->input('paid_date') === '') {
            $this->merge(['paid_date' => null]);
        }

        $account = $this->route('account');
        if ($account instanceof FinancialAccount && $account->is_card_purchase) {
            // Compra no cartão: não exigir/alterar conta bancária; null de due_date é ignorado.
            $this->request->remove('bank_account_id');

            if ($this->exists('due_date') && blank($this->input('due_date'))) {
                $this->request->remove('due_date');
            }
        }

        $this->mergeDateOnlyFields(['due_date', 'purchase_date', 'expected_date', 'paid_date']);

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
