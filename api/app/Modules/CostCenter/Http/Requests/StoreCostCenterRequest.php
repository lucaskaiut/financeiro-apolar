<?php

namespace App\Modules\CostCenter\Http\Requests;

use App\Modules\CostCenter\Enums\CostCenterType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCostCenterRequest extends FormRequest
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
            'bank' => ['nullable', 'string', 'max:255'],
            'agency' => ['nullable', 'string', 'max:50'],
            'account' => ['nullable', 'string', 'max:50'],
            'type' => ['required', 'string', Rule::in(CostCenterType::values())],
            'initial_balance' => ['nullable', 'numeric', 'min:0'],
            'status' => ['nullable', 'string', Rule::in(['active', 'inactive'])],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->filled('initial_balance')) {
            $this->merge([
                'initial_balance' => (float) $this->input('initial_balance'),
            ]);
        }
    }
}
