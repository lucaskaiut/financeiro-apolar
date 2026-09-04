<?php

namespace App\Modules\CostCenter\Policies;

use App\Modules\ACL\Enums\Permission;
use App\Modules\CostCenter\Models\CostCenter;
use App\Modules\Tenant\Support\TenantAuthorization;
use App\Modules\User\Models\User;

class CostCenterPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission(Permission::COST_CENTERS_VIEW);
    }

    public function view(User $user, CostCenter $costCenter): bool
    {
        return $this->sameTenant($costCenter)
            && $user->hasPermission(Permission::COST_CENTERS_VIEW);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission(Permission::COST_CENTERS_CREATE);
    }

    public function update(User $user, CostCenter $costCenter): bool
    {
        return $this->sameTenant($costCenter)
            && $user->hasPermission(Permission::COST_CENTERS_UPDATE);
    }

    public function delete(User $user, CostCenter $costCenter): bool
    {
        return $this->sameTenant($costCenter)
            && $user->hasPermission(Permission::COST_CENTERS_DELETE);
    }

    private function sameTenant(CostCenter $costCenter): bool
    {
        return TenantAuthorization::matchesCurrentTenant((int) $costCenter->tenant_id);
    }
}
