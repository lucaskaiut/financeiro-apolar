<?php

namespace App\Modules\Transfer\Policies;

use App\Modules\ACL\Enums\Permission;
use App\Modules\Tenant\Support\TenantAuthorization;
use App\Modules\Transfer\Models\Transfer;
use App\Modules\User\Models\User;

class TransferPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission(Permission::TRANSFERS_VIEW);
    }

    public function view(User $user, Transfer $transfer): bool
    {
        return $this->sameTenant($transfer)
            && $user->hasPermission(Permission::TRANSFERS_VIEW);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission(Permission::TRANSFERS_CREATE);
    }

    public function delete(User $user, Transfer $transfer): bool
    {
        return $this->sameTenant($transfer)
            && $user->hasPermission(Permission::TRANSFERS_DELETE);
    }

    private function sameTenant(Transfer $transfer): bool
    {
        return TenantAuthorization::matchesCurrentTenant((int) $transfer->tenant_id);
    }
}
