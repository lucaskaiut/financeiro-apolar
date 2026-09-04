import { useSessionStore } from '@/shared/stores/session.store'
import type { Permission } from '@/shared/constants/permissions'

export interface PermissionChecker {
  permissions: Permission[]
  can: (permission: Permission) => boolean
  canAny: (permissions: Permission[]) => boolean
}

export function usePermissions(): PermissionChecker {
  const permissions = useSessionStore((state) => state.permissions)

  return {
    permissions,
    can: (permission) => permissions.includes(permission),
    canAny: (list) => list.some((permission) => permissions.includes(permission)),
  }
}
