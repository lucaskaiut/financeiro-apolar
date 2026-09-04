<?php

namespace App\Modules\Assistant\Services;

use App\Modules\Tenant\Models\Tenant;
use RuntimeException;

final class AiConfigService
{
    /**
     * Resolve a configuração de IA do tenant atual.
     *
     * @return array{endpoint: string, api_key: string, model: string, temperature: float, max_tokens: ?int, system_prompt: ?string}
     */
    public function resolve(Tenant $tenant): array
    {
        if (! $tenant->ai_enabled) {
            throw new RuntimeException('O assistente de IA não está habilitado para esta organização.');
        }

        $endpoint = rtrim((string) $tenant->ai_endpoint, '/');

        if ($endpoint === '') {
            throw new RuntimeException('O endpoint da API de IA não foi configurado.');
        }

        if (blank($tenant->ai_api_key)) {
            throw new RuntimeException('A chave da API de IA não foi configurada.');
        }

        if (blank($tenant->ai_model)) {
            throw new RuntimeException('O modelo de IA não foi configurado.');
        }

        return [
            'endpoint' => $endpoint,
            'api_key' => (string) $tenant->ai_api_key,
            'model' => (string) $tenant->ai_model,
            'temperature' => $tenant->ai_temperature !== null ? (float) $tenant->ai_temperature : 0.2,
            'max_tokens' => $tenant->ai_max_tokens,
            'system_prompt' => $tenant->ai_system_prompt,
        ];
    }

    public function isConfigured(Tenant $tenant): bool
    {
        return $tenant->ai_enabled
            && filled($tenant->ai_endpoint)
            && filled($tenant->ai_api_key)
            && filled($tenant->ai_model);
    }
}
