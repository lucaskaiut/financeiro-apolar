<?php

namespace App\Modules\CreditCard\Http\Requests;

use App\Modules\Shared\Support\DateOnly;
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
            'category_id' => ['required', 'string', Rule::exists('categories', 'uuid')->where(fn ($q) => $q->where('tenant_id', $tenantId))],
            'subcategory_id' => ['nullable', 'string', Rule::exists('categories', 'uuid')->where(fn ($q) => $q->where('tenant_id', $tenantId))],
            'value' => ['required', 'numeric', 'gt:0'],
            'purchase_date' => ['required', 'date'],
            'observation' => ['nullable', 'string'],
            'installments' => ['nullable', 'array:quantity'],
            'installments.quantity' => ['required_with:installments', 'integer', 'min:1', 'max:120'],
        ];
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
    }
}
