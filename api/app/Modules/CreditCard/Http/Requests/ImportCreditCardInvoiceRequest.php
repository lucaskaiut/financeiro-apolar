<?php

namespace App\Modules\CreditCard\Http\Requests;

use App\Modules\Tenant\Support\Facades\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class ImportCreditCardInvoiceRequest extends FormRequest
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
            'paid_date' => ['required', 'date'],
            'bank_account_id' => [
                'required',
                'string',
                Rule::exists('bank_accounts', 'uuid')->where(fn ($q) => $q->where('tenant_id', $tenantId)),
            ],
            'items' => ['required', 'array', 'min:1'],
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
            'items.*.splits' => ['nullable', 'array', 'min:1'],
            'items.*.splits.*.description' => ['required', 'string', 'max:255'],
            'items.*.splits.*.value' => ['required', 'numeric', 'gt:0'],
            'items.*.splits.*.category_id' => [
                'required',
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

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $items = $this->input('items');

            if (! is_array($items)) {
                return;
            }

            foreach ($items as $index => $item) {
                if (($item['status'] ?? 'normal') === 'ignored') {
                    continue;
                }

                $splits = $item['splits'] ?? null;

                if (! is_array($splits) || $splits === []) {
                    if (empty($item['category_id'] ?? null)) {
                        $validator->errors()->add(
                            "items.{$index}.category_id",
                            'Informe a categoria da compra.',
                        );
                    }

                    continue;
                }

                $sum = round(array_sum(array_map(
                    fn ($split) => (float) ($split['value'] ?? 0),
                    $splits,
                )), 2);

                $total = round((float) ($item['value'] ?? 0), 2);

                if (abs($sum - $total) >= 0.01) {
                    $validator->errors()->add(
                        "items.{$index}.splits",
                        'A soma das partes do rateio deve ser igual ao valor da compra.',
                    );
                }
            }
        });
    }
}
