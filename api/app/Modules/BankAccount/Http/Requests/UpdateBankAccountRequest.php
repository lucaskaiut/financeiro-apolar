<?php

namespace App\Modules\BankAccount\Http\Requests;

use App\Modules\BankAccount\Enums\BankAccountType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateBankAccountRequest extends FormRequest
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
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'bank' => ['nullable', 'string', 'max:255'],
            'agency' => ['nullable', 'string', 'max:50'],
            'account' => ['nullable', 'string', 'max:50'],
            'type' => ['sometimes', 'required', Rule::in(BankAccountType::values())],
            'initial_balance' => ['nullable', 'numeric'],
            'status' => ['nullable', Rule::in(['active', 'inactive'])],
        ];
    }
}
