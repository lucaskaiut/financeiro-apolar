<?php

namespace App\Modules\Category\Http\Requests;

use App\Modules\Category\Enums\CategoryType;
use App\Modules\Category\Models\Category;
use App\Modules\Tenant\Support\Facades\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCategoryRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'string', Rule::in(CategoryType::values())],
            'color' => ['nullable', 'string', 'max:20'],
            'status' => ['nullable', 'string', Rule::in(['active', 'inactive'])],
            'parent_id' => [
                'nullable',
                'string',
                Rule::exists('categories', 'uuid')->where(fn ($q) => $q
                    ->where('tenant_id', TenantContext::tenantId())
                    ->whereNull('parent_id')),
            ],
        ];
    }

    protected function prepareForValidation(): void
    {
        if (! $this->filled('parent_id')) {
            return;
        }

        $parent = Category::query()
            ->where('uuid', $this->input('parent_id'))
            ->whereNull('parent_id')
            ->first();

        if ($parent !== null) {
            $this->merge(['type' => $parent->type?->value]);
        }
    }
}
