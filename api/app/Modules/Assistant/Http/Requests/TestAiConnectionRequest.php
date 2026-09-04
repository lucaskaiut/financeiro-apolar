<?php

namespace App\Modules\Assistant\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class TestAiConnectionRequest extends FormRequest
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
            'endpoint' => ['nullable', 'url', 'max:500'],
            'api_key' => ['nullable', 'string', 'max:2000'],
            'model' => ['nullable', 'string', 'max:255'],
        ];
    }
}
