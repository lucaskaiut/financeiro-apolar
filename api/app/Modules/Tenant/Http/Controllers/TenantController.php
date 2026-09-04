<?php

namespace App\Modules\Tenant\Http\Controllers;

use App\Modules\Shared\Http\Controllers\ApiController;
use App\Modules\Tenant\Http\Requests\UpdateTenantRequest;
use App\Modules\Tenant\Http\Resources\TenantResource;
use App\Modules\Tenant\Services\TenantService;
use App\Modules\Tenant\Support\Facades\TenantContext;
use Illuminate\Http\JsonResponse;

class TenantController extends ApiController
{
    public function __construct(private readonly TenantService $service) {}

    public function show(): JsonResponse
    {
        $tenant = TenantContext::tenant();

        $this->authorize('view', $tenant);

        return $this->success(TenantResource::make($tenant));
    }

    public function update(UpdateTenantRequest $request): JsonResponse
    {
        $tenant = TenantContext::tenant();

        $this->authorize('update', $tenant);

        $tenant = $this->service->update($tenant, $request->validated());

        return $this->success(TenantResource::make($tenant), 'Tenant atualizado com sucesso.');
    }
}
