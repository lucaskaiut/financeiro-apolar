<?php

namespace App\Modules\Reconciliation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ReconcileManyRequest extends FormRequest
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
            'transactions' => ['required', 'array', 'min:1'],
            'transactions.*' => ['required', 'string', 'uuid'],
            'accounts' => ['required', 'array', 'min:1'],
            'accounts.*' => ['required', 'string', 'uuid'],
        ];
    }
}
