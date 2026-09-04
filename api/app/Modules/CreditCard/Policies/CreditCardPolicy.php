<?php

namespace App\Modules\CreditCard\Policies;

use App\Modules\ACL\Enums\Permission;
use App\Modules\CreditCard\Models\CreditCard;
use App\Modules\Tenant\Support\TenantAuthorization;
use App\Modules\User\Models\User;

class CreditCardPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission(Permission::CREDIT_CARDS_VIEW);
    }

    public function view(User $user, CreditCard $creditCard): bool
    {
        return $this->sameTenant($creditCard) && $user->hasPermission(Permission::CREDIT_CARDS_VIEW);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission(Permission::CREDIT_CARDS_CREATE);
    }

    public function update(User $user, CreditCard $creditCard): bool
    {
        return $this->sameTenant($creditCard) && $user->hasPermission(Permission::CREDIT_CARDS_UPDATE);
    }

    public function delete(User $user, CreditCard $creditCard): bool
    {
        return $this->sameTenant($creditCard) && $user->hasPermission(Permission::CREDIT_CARDS_DELETE);
    }

    private function sameTenant(CreditCard $creditCard): bool
    {
        return TenantAuthorization::matchesCurrentTenant((int) $creditCard->tenant_id);
    }
}
