<?php

namespace App\Modules\Reconciliation\Http\Requests;

use App\Modules\Account\Enums\AccountType;
use App\Modules\Tenant\Support\Facades\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateFromTransactionRequest extends FormRequest
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
        return [
            'type' => ['required', 'string', Rule::in(AccountType::values())],
            'description' => ['required', 'string', 'max:255'],
            'category_id' => [
                'required',
                'string',
                Rule::exists('categories', 'uuid')->where(fn ($q) => $q->where('tenant_id', TenantContext::tenantId())),
            ],
            'bank_account_id' => [
                'nullable',
                'string',
                Rule::exists('bank_accounts', 'uuid')->where(fn ($q) => $q->where('tenant_id', TenantContext::tenantId())),
            ],
            'cost_center_id' => [
                'nullable',
                'string',
                Rule::exists('cost_centers', 'uuid')->where(fn ($q) => $q->where('tenant_id', TenantContext::tenantId())),
            ],
            'value' => ['nullable', 'numeric', 'gt:0'],
            'due_date' => ['nullable', 'date'],
            'observation' => ['nullable', 'string'],
            'account_ids' => ['nullable', 'array'],
            'account_ids.*' => [
                'required',
                'string',
                'uuid',
                Rule::exists('financial_accounts', 'uuid')->where(fn ($q) => $q->where('tenant_id', TenantContext::tenantId())),
            ],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->filled('value')) {
            $this->merge(['value' => (float) $this->input('value')]);
        }

        if ($this->input('cost_center_id') === '') {
            $this->merge(['cost_center_id' => null]);
        }
    }
}
