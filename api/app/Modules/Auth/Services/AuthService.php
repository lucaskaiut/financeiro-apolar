<?php

namespace App\Modules\Auth\Services;

use App\Modules\ACL\Enums\DefaultRole;
use App\Modules\ACL\Services\RoleService;
use App\Modules\Audit\Enums\AuditAction;
use App\Modules\Audit\Services\AuditLogService;
use App\Modules\Auth\DTOs\AuthenticatedUser;
use App\Modules\Auth\DTOs\NewTenantData;
use App\Modules\Auth\DTOs\NewUserData;
use App\Modules\Auth\DTOs\RegisterResult;
use App\Modules\Tenant\Models\Tenant;
use App\Modules\Tenant\Services\MasterTenantAccessService;
use App\Modules\Tenant\Services\TenantService;
use App\Modules\Tenant\Support\CurrentTenant;
use App\Modules\User\Models\User;
use App\Modules\User\Services\UserService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\PersonalAccessToken;

class AuthService
{
    private const TOKEN_NAME = 'auth_token';

    public function __construct(
        private readonly TenantService $tenants,
        private readonly RoleService $roles,
        private readonly UserService $users,
        private readonly CurrentTenant $context,
        private readonly MasterTenantAccessService $masterAccess,
        private readonly AuditLogService $audit,
    ) {}

    public function register(
        NewTenantData $tenantData,
        NewUserData $userData,
    ): RegisterResult {
        [$tenant, $user] = DB::transaction(
            function () use ($tenantData, $userData): array {
                $tenant = $this->tenants->create($tenantData->toArray());

                $roles = $this->roles->createDefaultRolesFor($tenant);

                $user = $this->users->createForTenant($tenant, $userData->toArray());

                if ($tenant->isUmbrella()) {
                    $user->forceFill(['is_master' => true])->save();
                }

                $user->assignRole($roles[DefaultRole::ADMINISTRATOR->value]);

                return [$tenant, $user];
            }
        );

        $this->context->set($tenant);

        return new RegisterResult(
            user: $user->load('roles.permissions'),
            tenant: $tenant,
            token: $this->authenticate($user),
        );
    }

    /**
     * @throws ValidationException
     */
    public function login(string $email, string $password): AuthenticatedUser
    {
        $user = User::query()
            ->withoutTenancy()
            ->where('email', $email)
            ->first();

        if ($user === null || ! Hash::check($password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['As credenciais informadas são inválidas.'],
            ]);
        }

        $tenant = $user->tenant;
        $availableTenants = $this->masterAccess->availableTenants($user);

        $this->context->set($tenant);

        if ($user->is_master) {
            $this->audit->record($user, AuditAction::MasterLogin, $tenant);
        }

        return new AuthenticatedUser(
            user: $user->load('roles.permissions'),
            tenant: $tenant,
            token: $this->authenticate($user),
            availableTenants: $availableTenants,
        );
    }

    public function logout(User $user): void
    {
        $token = $user->currentAccessToken();

        if ($token instanceof PersonalAccessToken) {
            $token->delete();
        }

        $request = request();

        if ($request->hasSession()) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }
    }

    /**
     * @return Collection<int, Tenant>
     */
    public function availableTenantsFor(User $user): Collection
    {
        return $this->masterAccess->availableTenants($user);
    }

    /**
     * Autentica via sessão (SPA stateful, cookie HttpOnly) quando disponível;
     * caso contrário emite um personal access token (clientes de API).
     */
    private function authenticate(User $user): ?string
    {
        $request = request();

        if ($request->hasSession()) {
            Auth::guard('web')->login($user);
            $request->session()->regenerate();

            return null;
        }

        return $user->createToken(self::TOKEN_NAME)->plainTextToken;
    }
}
