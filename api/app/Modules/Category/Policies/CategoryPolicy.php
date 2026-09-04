<?php

namespace App\Modules\Category\Policies;

use App\Modules\ACL\Enums\Permission;
use App\Modules\Category\Models\Category;
use App\Modules\Tenant\Support\TenantAuthorization;
use App\Modules\User\Models\User;

class CategoryPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission(Permission::CATEGORIES_VIEW);
    }

    public function view(User $user, Category $category): bool
    {
        return $this->sameTenant($category)
            && $user->hasPermission(Permission::CATEGORIES_VIEW);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission(Permission::CATEGORIES_CREATE);
    }

    public function update(User $user, Category $category): bool
    {
        return $this->sameTenant($category)
            && $user->hasPermission(Permission::CATEGORIES_UPDATE);
    }

    public function delete(User $user, Category $category): bool
    {
        return $this->sameTenant($category)
            && $user->hasPermission(Permission::CATEGORIES_DELETE);
    }

    private function sameTenant(Category $category): bool
    {
        return TenantAuthorization::matchesCurrentTenant((int) $category->tenant_id);
    }
}
