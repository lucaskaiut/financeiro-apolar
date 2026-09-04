<?php

namespace App\Modules\Recurrence\Policies;

use App\Modules\ACL\Enums\Permission;
use App\Modules\Recurrence\Models\Recurrence;
use App\Modules\Tenant\Support\TenantAuthorization;
use App\Modules\User\Models\User;

class RecurrencePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission(Permission::RECURRENCES_VIEW);
    }

    public function view(User $user, Recurrence $recurrence): bool
    {
        return $this->sameTenant($recurrence)
            && $user->hasPermission(Permission::RECURRENCES_VIEW);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission(Permission::RECURRENCES_CREATE);
    }

    public function update(User $user, Recurrence $recurrence): bool
    {
        return $this->sameTenant($recurrence)
            && $user->hasPermission(Permission::RECURRENCES_UPDATE);
    }

    public function delete(User $user, Recurrence $recurrence): bool
    {
        return $this->sameTenant($recurrence)
            && $user->hasPermission(Permission::RECURRENCES_DELETE);
    }

    private function sameTenant(Recurrence $recurrence): bool
    {
        return TenantAuthorization::matchesCurrentTenant((int) $recurrence->tenant_id);
    }
}
