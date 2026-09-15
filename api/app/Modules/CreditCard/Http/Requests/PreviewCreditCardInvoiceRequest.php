<?php

namespace App\Modules\CreditCard\Http\Requests;

use App\Modules\Tenant\Support\Facades\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PreviewCreditCardInvoiceRequest extends FormRequest
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
            'file' => ['required', 'file', 'mimes:xlsx,xls', 'max:20480'],
            'reference_month' => ['required', 'date_format:Y-m'],
            'category_id' => [
                'required',
                'string',
                Rule::exists('categories', 'uuid')->where(fn ($q) => $q->where('tenant_id', $tenantId)),
            ],
            'cost_center_id' => [
                'nullable',
                'string',
                Rule::exists('cost_centers', 'uuid')->where(fn ($q) => $q->where('tenant_id', $tenantId)),
            ],
        ];
    }
}
