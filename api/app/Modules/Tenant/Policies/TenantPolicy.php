<?php

namespace App\Modules\Tenant\Policies;

use App\Modules\ACL\Enums\Permission;
use App\Modules\Tenant\Models\Tenant;
use App\Modules\Tenant\Support\TenantAuthorization;
use App\Modules\User\Models\User;

class TenantPolicy
{
    public function view(User $user, Tenant $tenant): bool
    {
        return TenantAuthorization::matchesCurrentTenant($tenant->getKey())
            && $user->hasPermission(Permission::TENANT_READ);
    }

    public function update(User $user, Tenant $tenant): bool
    {
        return TenantAuthorization::matchesCurrentTenant($tenant->getKey())
            && $user->hasPermission(Permission::TENANT_UPDATE);
    }
}
