<?php

namespace App\Modules\Reconciliation\Http\Requests;

use App\Modules\Tenant\Support\Facades\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ImportOfxRequest extends FormRequest
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
            'bank_account_id' => [
                'required',
                'string',
                Rule::exists('bank_accounts', 'uuid')->where(fn ($q) => $q->where('tenant_id', TenantContext::tenantId())),
            ],
            'content' => ['nullable', 'string', 'min:1', 'required_without:file'],
            'file' => ['nullable', 'file', 'mimes:ofx,xml,xls,xlsx', 'max:20480', 'required_without:content'],
        ];
    }
}
