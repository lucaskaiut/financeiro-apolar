<?php

namespace App\Modules\Account\Policies;

use App\Modules\Account\Models\FinancialAccount;
use App\Modules\ACL\Enums\Permission;
use App\Modules\Tenant\Support\TenantAuthorization;
use App\Modules\User\Models\User;

class FinancialAccountPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission(Permission::ACCOUNTS_VIEW);
    }

    public function view(User $user, FinancialAccount $account): bool
    {
        return $this->sameTenant($account)
            && $user->hasPermission(Permission::ACCOUNTS_VIEW);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission(Permission::ACCOUNTS_CREATE);
    }

    public function update(User $user, FinancialAccount $account): bool
    {
        return $this->sameTenant($account)
            && $user->hasPermission(Permission::ACCOUNTS_UPDATE);
    }

    public function delete(User $user, FinancialAccount $account): bool
    {
        return $this->sameTenant($account)
            && $user->hasPermission(Permission::ACCOUNTS_DELETE);
    }

    public function settle(User $user, FinancialAccount $account): bool
    {
        return $this->sameTenant($account)
            && $user->hasPermission(Permission::ACCOUNTS_SETTLE);
    }

    private function sameTenant(FinancialAccount $account): bool
    {
        return TenantAuthorization::matchesCurrentTenant((int) $account->tenant_id);
    }
}
