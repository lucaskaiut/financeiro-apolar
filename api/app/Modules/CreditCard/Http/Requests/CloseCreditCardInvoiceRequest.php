<?php

namespace App\Modules\CreditCard\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CloseCreditCardInvoiceRequest extends FormRequest
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
            'reference_month' => ['required', 'date_format:Y-m'],
        ];
    }
}
