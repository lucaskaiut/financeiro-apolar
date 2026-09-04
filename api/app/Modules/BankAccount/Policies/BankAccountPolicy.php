<?php

namespace App\Modules\BankAccount\Policies;

use App\Modules\ACL\Enums\Permission;
use App\Modules\BankAccount\Models\BankAccount;
use App\Modules\Tenant\Support\TenantAuthorization;
use App\Modules\User\Models\User;

class BankAccountPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission(Permission::BANK_ACCOUNTS_VIEW);
    }

    public function view(User $user, BankAccount $bankAccount): bool
    {
        return $this->sameTenant($bankAccount)
            && $user->hasPermission(Permission::BANK_ACCOUNTS_VIEW);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission(Permission::BANK_ACCOUNTS_CREATE);
    }

    public function update(User $user, BankAccount $bankAccount): bool
    {
        return $this->sameTenant($bankAccount)
            && $user->hasPermission(Permission::BANK_ACCOUNTS_UPDATE);
    }

    public function delete(User $user, BankAccount $bankAccount): bool
    {
        return $this->sameTenant($bankAccount)
            && $user->hasPermission(Permission::BANK_ACCOUNTS_DELETE);
    }

    private function sameTenant(BankAccount $bankAccount): bool
    {
        return TenantAuthorization::matchesCurrentTenant((int) $bankAccount->tenant_id);
    }
}
