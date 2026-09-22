<?php

namespace App\Modules\Reconciliation\Http\Requests;

use App\Modules\Account\Http\Requests\StoreAccountRequest;
use App\Modules\Tenant\Support\Facades\TenantContext;
use Illuminate\Validation\Rule;

class CreateFromTransactionRequest extends StoreAccountRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $rules = parent::rules();

        // Compras no cartão são liquidadas pela fatura e não entram na conciliação bancária.
        $rules['credit_card_id'] = ['prohibited'];
        $rules['account_ids'] = ['nullable', 'array'];
        $rules['account_ids.*'] = [
            'required',
            'string',
            'uuid',
            Rule::exists('financial_accounts', 'uuid')->where(fn ($q) => $q->where('tenant_id', TenantContext::tenantId())),
        ];

        return $rules;
    }
}
