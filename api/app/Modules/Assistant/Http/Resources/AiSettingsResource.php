<?php

namespace App\Modules\Assistant\Http\Resources;

use App\Modules\Assistant\Services\AiConfigService;
use App\Modules\Tenant\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Tenant
 */
class AiSettingsResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $configured = app(AiConfigService::class)->isConfigured($this->resource);

        return [
            'enabled' => (bool) $this->ai_enabled,
            'endpoint' => $this->ai_endpoint,
            'model' => $this->ai_model,
            'temperature' => $this->ai_temperature !== null ? (float) $this->ai_temperature : null,
            'max_tokens' => $this->ai_max_tokens,
            'system_prompt' => $this->ai_system_prompt,
            'has_api_key' => filled($this->ai_api_key),
            'configured' => $configured,
        ];
    }
}
