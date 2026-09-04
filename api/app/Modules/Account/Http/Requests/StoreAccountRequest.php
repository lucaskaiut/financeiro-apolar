<?php

namespace App\Modules\Account\Http\Requests;

use App\Modules\Account\Enums\AccountType;
use App\Modules\Category\Models\Category;
use App\Modules\Tenant\Support\Facades\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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

        return [
            'type' => ['required', 'string', Rule::in(AccountType::values())],
            'description' => ['required', 'string', 'max:255'],
            'counterparty' => ['nullable', 'string', 'max:255'],
            'bank_account_id' => [
                'nullable',
                'string',
                'required_without:credit_card_id',
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
                'required_without:allocations',
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
            'due_date' => ['required', 'date'],
            'expected_date' => ['nullable', 'date'],
            'observation' => ['nullable', 'string'],
            'installments' => ['nullable', 'array:quantity,interval'],
            'installments.quantity' => ['required_with:installments', 'integer', 'min:1', 'max:120'],
            'installments.interval' => ['nullable', 'string', Rule::in(['daily', 'weekly', 'monthly'])],
            'allocations' => ['nullable', 'array', 'min:1'],
            'allocations.*.cost_center_id' => [
                'nullable',
                'string',
                Rule::exists('cost_centers', 'uuid')->where(fn ($q) => $q->where('tenant_id', $tenantId)),
            ],
            'allocations.*.company_id' => [
                'nullable',
                'string',
                Rule::exists('companies', 'uuid')->where(fn ($q) => $q->where('tenant_id', $tenantId)),
            ],
            'allocations.*.category_id' => [
                'nullable',
                'string',
                Rule::exists('categories', 'uuid')->where(fn ($q) => $q->where('tenant_id', $tenantId)),
            ],
            'allocations.*.subcategory_id' => [
                'nullable',
                'string',
                Rule::exists('categories', 'uuid')->where(fn ($q) => $q->where('tenant_id', $tenantId)),
            ],
            'allocations.*.value' => ['nullable', 'numeric', 'gt:0'],
            'allocations.*.percentage' => ['nullable', 'numeric', 'gt:0', 'lte:100'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->filled('value')) {
            $this->merge(['value' => (float) $this->input('value')]);
        }

        $this->fillCategoryFromSubcategory();
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
