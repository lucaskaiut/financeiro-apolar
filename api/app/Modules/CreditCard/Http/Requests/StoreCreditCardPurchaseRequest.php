<?php

namespace App\Modules\CreditCard\Http\Requests;

use App\Modules\Tenant\Support\Facades\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
            'category_id' => ['required_without:allocations', 'nullable', 'string', Rule::exists('categories', 'uuid')->where(fn ($q) => $q->where('tenant_id', $tenantId))],
            'subcategory_id' => ['nullable', 'string', Rule::exists('categories', 'uuid')->where(fn ($q) => $q->where('tenant_id', $tenantId))],
            'value' => ['required', 'numeric', 'gt:0'],
            'due_date' => ['required', 'date'],
            'observation' => ['nullable', 'string'],
            'allocations' => ['nullable', 'array', 'min:1'],
            'allocations.*.cost_center_id' => ['nullable', 'string', Rule::exists('cost_centers', 'uuid')->where(fn ($q) => $q->where('tenant_id', $tenantId))],
            'allocations.*.company_id' => ['nullable', 'string', Rule::exists('companies', 'uuid')->where(fn ($q) => $q->where('tenant_id', $tenantId))],
            'allocations.*.category_id' => ['nullable', 'string', Rule::exists('categories', 'uuid')->where(fn ($q) => $q->where('tenant_id', $tenantId))],
            'allocations.*.value' => ['nullable', 'numeric', 'gt:0'],
            'allocations.*.percentage' => ['nullable', 'numeric', 'gt:0', 'lte:100'],
        ];
    }
}
