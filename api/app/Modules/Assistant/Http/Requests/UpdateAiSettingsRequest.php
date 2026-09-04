<?php

namespace App\Modules\Assistant\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAiSettingsRequest extends FormRequest
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
            'enabled' => ['required', 'boolean'],
            'endpoint' => ['nullable', 'url', 'max:500'],
            'api_key' => ['nullable', 'string', 'max:2000'],
            'model' => ['nullable', 'string', 'max:255'],
            'temperature' => ['nullable', 'numeric', 'min:0', 'max:2'],
            'max_tokens' => ['nullable', 'integer', 'min:1', 'max:200000'],
            'system_prompt' => ['nullable', 'string', 'max:5000'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('enabled')) {
            $this->merge(['enabled' => filter_var($this->input('enabled'), FILTER_VALIDATE_BOOLEAN)]);
        }

        if ($this->filled('endpoint')) {
            $this->merge(['endpoint' => rtrim(trim((string) $this->input('endpoint')), '/')]);
        }

        if ($this->filled('temperature')) {
            $this->merge(['temperature' => (float) $this->input('temperature')]);
        }
    }
}
