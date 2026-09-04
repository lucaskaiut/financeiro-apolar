<?php

namespace App\Modules\Auth\Http\Controllers;

use App\Modules\ACL\Http\Resources\RoleResource;
use App\Modules\Auth\DTOs\AuthenticatedUser;
use App\Modules\Auth\Http\Requests\LoginRequest;
use App\Modules\Auth\Http\Requests\SelectTenantRequest;
use App\Modules\Auth\Services\AuthService;
use App\Modules\Shared\Http\Controllers\ApiController;
use App\Modules\Tenant\Http\Resources\AvailableTenantResource;
use App\Modules\Tenant\Http\Resources\TenantResource;
use App\Modules\Tenant\Models\Tenant;
use App\Modules\Tenant\Services\TenantSwitchService;
use App\Modules\Tenant\Support\Facades\TenantContext;
use App\Modules\User\Http\Resources\UserResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthController extends ApiController
{
    public function __construct(
        private readonly AuthService $service,
        private readonly TenantSwitchService $tenantSwitch,
    ) {}

    public function login(LoginRequest $request): JsonResponse
    {
        $result = $this->service->login(
            $request->validated('email'),
            $request->validated('password'),
        );

        return $this->success($this->authPayload($result), 'Login realizado com sucesso.');
    }

    public function logout(Request $request): JsonResponse
    {
        $this->service->logout($request->user());

        return $this->success(null, 'Logout realizado com sucesso.');
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user()->load('roles.permissions');
        /** @var Tenant|null $tenant */
        $tenant = TenantContext::tenant();

        return $this->success([
            'user' => UserResource::make($user),
            'tenant' => TenantResource::make($tenant),
            'roles' => RoleResource::collection($user->roles),
            'permissions' => $user->permissionValues()->all(),
            'is_master' => (bool) $user->is_master,
            'available_tenants' => AvailableTenantResource::collection(
                $this->service->availableTenantsFor($user),
            ),
        ]);
    }

    public function selectTenant(SelectTenantRequest $request): JsonResponse
    {
        $tenant = $this->tenantSwitch->select(
            $request->user(),
            $request->validated('tenant_id'),
        );

        return $this->success([
            'tenant' => AvailableTenantResource::make($tenant),
        ], 'Tenant selecionado com sucesso.');
    }

    /**
     * @return array<string, mixed>
     */
    private function authPayload(AuthenticatedUser $result): array
    {
        return [
            'token' => $result->token,
            'token_type' => 'Bearer',
            'user' => UserResource::make($result->user),
            'tenant' => TenantResource::make($result->tenant),
            'is_master' => (bool) $result->user->is_master,
            'available_tenants' => AvailableTenantResource::collection($result->availableTenants),
        ];
    }
}
