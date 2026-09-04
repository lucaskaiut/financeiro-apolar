<?php

namespace App\Modules\Assistant\Http\Controllers;

use App\Modules\Assistant\Http\Requests\TestAiConnectionRequest;
use App\Modules\Assistant\Http\Requests\UpdateAiSettingsRequest;
use App\Modules\Assistant\Http\Resources\AiSettingsResource;
use App\Modules\Assistant\Services\OpenAiClient;
use App\Modules\Shared\Http\Controllers\ApiController;
use App\Modules\Tenant\Models\Tenant;
use App\Modules\Tenant\Support\Facades\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class AiSettingsController extends ApiController
{
    public function __construct(private readonly OpenAiClient $client) {}

    public function show(): JsonResponse
    {
        return $this->success(AiSettingsResource::make(TenantContext::tenant()));
    }

    public function update(UpdateAiSettingsRequest $request): JsonResponse
    {
        $tenant = TenantContext::tenant();

        $validated = $request->validated();

        $tenant->ai_enabled = $validated['enabled'];
        $tenant->ai_endpoint = $validated['endpoint'] ?? null;
        $tenant->ai_model = $validated['model'] ?? null;
        $tenant->ai_temperature = $validated['temperature'] ?? null;
        $tenant->ai_max_tokens = $validated['max_tokens'] ?? null;
        $tenant->ai_system_prompt = $validated['system_prompt'] ?? null;

        if (filled($validated['api_key'] ?? null)) {
            $tenant->ai_api_key = $validated['api_key'];
        }

        $tenant->save();

        return $this->success(
            AiSettingsResource::make($tenant->refresh()),
            'Configurações de IA salvas com sucesso.',
        );
    }

    public function test(TestAiConnectionRequest $request): JsonResponse
    {
        /** @var Tenant $tenant */
        $tenant = TenantContext::tenant();

        $endpoint = $request->string('endpoint')->toString() ?: (string) $tenant->ai_endpoint;
        $apiKey = $request->string('api_key')->toString() ?: (string) $tenant->ai_api_key;
        $model = $request->string('model')->toString() ?: (string) $tenant->ai_model;

        if ($endpoint === '' || $apiKey === '' || $model === '') {
            throw ValidationException::withMessages([
                'model' => ['Preencha endpoint, chave da API e modelo antes de testar.'],
            ]);
        }

        $result = $this->client->testConnection([
            'endpoint' => rtrim($endpoint, '/'),
            'api_key' => $apiKey,
            'model' => $model,
        ]);

        return $this->success($result);
    }
}
