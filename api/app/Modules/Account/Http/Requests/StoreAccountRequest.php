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
        return [
            'type' => ['required', 'string', Rule::in(AccountType::values())],
            'description' => ['required', 'string', 'max:255'],
            'counterparty' => ['nullable', 'string', 'max:255'],
            'cost_center_id' => [
                'required',
                'string',
                Rule::exists('cost_centers', 'uuid')->where(fn ($q) => $q->where('tenant_id', TenantContext::tenantId())),
            ],
            'category_id' => [
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
            'value' => ['required', 'numeric', 'gt:0'],
            'due_date' => ['required', 'date'],
            'expected_date' => ['nullable', 'date'],
            'observation' => ['nullable', 'string'],
            'installments' => ['nullable', 'array:quantity,interval'],
            'installments.quantity' => ['required_with:installments', 'integer', 'min:1', 'max:120'],
            'installments.interval' => ['nullable', 'string', Rule::in(['daily', 'weekly', 'monthly'])],
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
