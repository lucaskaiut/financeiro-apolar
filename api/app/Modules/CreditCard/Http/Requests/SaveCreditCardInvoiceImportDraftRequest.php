<?php

namespace App\Modules\CreditCard\Http\Requests;

use App\Modules\Tenant\Support\Facades\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveCreditCardInvoiceImportDraftRequest extends FormRequest
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
            'reference_month' => ['required', 'date_format:Y-m'],
            'paid_date' => ['nullable', 'date'],
            'bank_account_id' => [
                'nullable',
                'string',
                Rule::exists('bank_accounts', 'uuid')->where(fn ($q) => $q->where('tenant_id', $tenantId)),
            ],
            'category_id' => [
                'nullable',
                'string',
                Rule::exists('categories', 'uuid')->where(fn ($q) => $q->where('tenant_id', $tenantId)),
            ],
            'cost_center_id' => [
                'nullable',
                'string',
                Rule::exists('cost_centers', 'uuid')->where(fn ($q) => $q->where('tenant_id', $tenantId)),
            ],
            'due_date' => ['nullable', 'date'],
            'step' => ['nullable', 'integer', Rule::in([1, 2, 3])],
            'source_filename' => ['nullable', 'string', 'max:255'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.id' => ['nullable', 'string', 'max:64'],
            'items.*.description' => ['required', 'string', 'max:255'],
            'items.*.purchase_date' => ['required', 'date'],
            'items.*.value' => ['required', 'numeric', 'gt:0'],
            'items.*.category_id' => [
                'nullable',
                'string',
                Rule::exists('categories', 'uuid')->where(fn ($q) => $q->where('tenant_id', $tenantId)),
            ],
            'items.*.subcategory_id' => [
                'nullable',
                'string',
                Rule::exists('categories', 'uuid')->where(fn ($q) => $q->where('tenant_id', $tenantId)),
            ],
            'items.*.cost_center_id' => [
                'nullable',
                'string',
                Rule::exists('cost_centers', 'uuid')->where(fn ($q) => $q->where('tenant_id', $tenantId)),
            ],
            'items.*.status' => ['nullable', 'string', Rule::in(['normal', 'ignored'])],
            'items.*.is_duplicate' => ['nullable', 'boolean'],
            'items.*.existing' => ['nullable', 'array'],
            'items.*.existing.date' => ['nullable', 'date'],
            'items.*.existing.value' => ['nullable', 'numeric'],
            'items.*.existing.description' => ['nullable', 'string'],
            'items.*.splits' => ['nullable', 'array'],
            'items.*.splits.*.id' => ['nullable', 'string', 'max:64'],
            'items.*.splits.*.description' => ['nullable', 'string', 'max:255'],
            'items.*.splits.*.value' => ['nullable', 'numeric', 'gt:0'],
            'items.*.splits.*.category_id' => [
                'nullable',
                'string',
                Rule::exists('categories', 'uuid')->where(fn ($q) => $q->where('tenant_id', $tenantId)),
            ],
            'items.*.splits.*.subcategory_id' => [
                'nullable',
                'string',
                Rule::exists('categories', 'uuid')->where(fn ($q) => $q->where('tenant_id', $tenantId)),
            ],
            'items.*.splits.*.cost_center_id' => [
                'nullable',
                'string',
                Rule::exists('cost_centers', 'uuid')->where(fn ($q) => $q->where('tenant_id', $tenantId)),
            ],
        ];
    }
}
