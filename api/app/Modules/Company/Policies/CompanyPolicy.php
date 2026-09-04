<?php

namespace App\Modules\Company\Policies;

use App\Modules\ACL\Enums\Permission;
use App\Modules\Company\Models\Company;
use App\Modules\Tenant\Support\TenantAuthorization;
use App\Modules\User\Models\User;

class CompanyPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission(Permission::COMPANIES_VIEW);
    }

    public function view(User $user, Company $company): bool
    {
        return $this->sameTenant($company)
            && $user->hasPermission(Permission::COMPANIES_VIEW);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission(Permission::COMPANIES_CREATE);
    }

    public function update(User $user, Company $company): bool
    {
        return $this->sameTenant($company)
            && $user->hasPermission(Permission::COMPANIES_UPDATE);
    }

    public function delete(User $user, Company $company): bool
    {
        return $this->sameTenant($company)
            && $user->hasPermission(Permission::COMPANIES_DELETE);
    }

    private function sameTenant(Company $company): bool
    {
        return TenantAuthorization::matchesCurrentTenant((int) $company->tenant_id);
    }
}
