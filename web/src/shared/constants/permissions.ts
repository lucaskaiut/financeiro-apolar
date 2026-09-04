export const Permission = {
  USER_CREATE: 'user.create',
  USER_READ: 'user.read',
  USER_UPDATE: 'user.update',
  USER_DELETE: 'user.delete',

  TENANT_READ: 'tenant.read',
  TENANT_UPDATE: 'tenant.update',

  ROLE_CREATE: 'role.create',
  ROLE_READ: 'role.read',
  ROLE_UPDATE: 'role.update',
  ROLE_DELETE: 'role.delete',

  COST_CENTERS_VIEW: 'cost_centers.view',
  COST_CENTERS_CREATE: 'cost_centers.create',
  COST_CENTERS_UPDATE: 'cost_centers.update',
  COST_CENTERS_DELETE: 'cost_centers.delete',

  CATEGORIES_VIEW: 'categories.view',
  CATEGORIES_CREATE: 'categories.create',
  CATEGORIES_UPDATE: 'categories.update',
  CATEGORIES_DELETE: 'categories.delete',

  ACCOUNTS_VIEW: 'accounts.view',
  ACCOUNTS_CREATE: 'accounts.create',
  ACCOUNTS_UPDATE: 'accounts.update',
  ACCOUNTS_DELETE: 'accounts.delete',
  ACCOUNTS_SETTLE: 'accounts.settle',

  RECURRENCES_VIEW: 'recurrences.view',
  RECURRENCES_CREATE: 'recurrences.create',
  RECURRENCES_UPDATE: 'recurrences.update',
  RECURRENCES_DELETE: 'recurrences.delete',

  TRANSFERS_VIEW: 'transfers.view',
  TRANSFERS_CREATE: 'transfers.create',
  TRANSFERS_DELETE: 'transfers.delete',

  CASH_FLOW_VIEW: 'cash_flow.view',

  RECONCILIATION_VIEW: 'reconciliation.view',
  RECONCILIATION_EXECUTE: 'reconciliation.execute',
  RECONCILIATION_UNDO: 'reconciliation.undo',

  REPORTS_VIEW: 'reports.view',
  REPORTS_EXPORT: 'reports.export',

  AUDIT_VIEW: 'audit.view',

  ASSISTANT_VIEW: 'assistant.view',
  ASSISTANT_CONFIGURE: 'assistant.configure',
} as const

export type Permission = (typeof Permission)[keyof typeof Permission]

export interface PermissionGroup {
  label: string
  permissions: Array<{ value: Permission; label: string }>
}

export const PERMISSION_GROUPS: PermissionGroup[] = [
  {
    label: 'Usuários',
    permissions: [
      { value: Permission.USER_READ, label: 'Visualizar usuários' },
      { value: Permission.USER_CREATE, label: 'Criar usuários' },
      { value: Permission.USER_UPDATE, label: 'Editar usuários' },
      { value: Permission.USER_DELETE, label: 'Remover usuários' },
    ],
  },
  {
    label: 'Organização',
    permissions: [
      { value: Permission.TENANT_READ, label: 'Visualizar dados da organização' },
      { value: Permission.TENANT_UPDATE, label: 'Editar dados da organização' },
    ],
  },
  {
    label: 'Perfis de acesso',
    permissions: [
      { value: Permission.ROLE_READ, label: 'Visualizar perfis' },
      { value: Permission.ROLE_CREATE, label: 'Criar perfis' },
      { value: Permission.ROLE_UPDATE, label: 'Editar perfis' },
      { value: Permission.ROLE_DELETE, label: 'Remover perfis' },
    ],
  },
  {
    label: 'Centros de custo',
    permissions: [
      { value: Permission.COST_CENTERS_VIEW, label: 'Visualizar centros de custo' },
      { value: Permission.COST_CENTERS_CREATE, label: 'Criar centros de custo' },
      { value: Permission.COST_CENTERS_UPDATE, label: 'Editar centros de custo' },
      { value: Permission.COST_CENTERS_DELETE, label: 'Remover centros de custo' },
    ],
  },
  {
    label: 'Categorias',
    permissions: [
      { value: Permission.CATEGORIES_VIEW, label: 'Visualizar categorias' },
      { value: Permission.CATEGORIES_CREATE, label: 'Criar categorias' },
      { value: Permission.CATEGORIES_UPDATE, label: 'Editar categorias' },
      { value: Permission.CATEGORIES_DELETE, label: 'Remover categorias' },
    ],
  },
  {
    label: 'Contas a pagar/receber',
    permissions: [
      { value: Permission.ACCOUNTS_VIEW, label: 'Visualizar contas' },
      { value: Permission.ACCOUNTS_CREATE, label: 'Criar contas' },
      { value: Permission.ACCOUNTS_UPDATE, label: 'Editar contas' },
      { value: Permission.ACCOUNTS_DELETE, label: 'Remover contas' },
      { value: Permission.ACCOUNTS_SETTLE, label: 'Registrar baixas' },
    ],
  },
  {
    label: 'Recorrências',
    permissions: [
      { value: Permission.RECURRENCES_VIEW, label: 'Visualizar recorrências' },
      { value: Permission.RECURRENCES_CREATE, label: 'Criar recorrências' },
      { value: Permission.RECURRENCES_UPDATE, label: 'Editar recorrências' },
      { value: Permission.RECURRENCES_DELETE, label: 'Remover recorrências' },
    ],
  },
  {
    label: 'Transferências',
    permissions: [
      { value: Permission.TRANSFERS_VIEW, label: 'Visualizar transferências' },
      { value: Permission.TRANSFERS_CREATE, label: 'Realizar transferências' },
      { value: Permission.TRANSFERS_DELETE, label: 'Remover transferências' },
    ],
  },
  {
    label: 'Fluxo de caixa',
    permissions: [{ value: Permission.CASH_FLOW_VIEW, label: 'Visualizar fluxo de caixa' }],
  },
  {
    label: 'Conciliação bancária',
    permissions: [
      { value: Permission.RECONCILIATION_VIEW, label: 'Visualizar conciliação' },
      { value: Permission.RECONCILIATION_EXECUTE, label: 'Executar conciliação' },
      { value: Permission.RECONCILIATION_UNDO, label: 'Desfazer conciliação' },
    ],
  },
  {
    label: 'Relatórios',
    permissions: [
      { value: Permission.REPORTS_VIEW, label: 'Visualizar relatórios' },
      { value: Permission.REPORTS_EXPORT, label: 'Exportar relatórios' },
    ],
  },
  {
    label: 'Auditoria',
    permissions: [{ value: Permission.AUDIT_VIEW, label: 'Visualizar auditoria' }],
  },
  {
    label: 'Assistente de IA',
    permissions: [
      { value: Permission.ASSISTANT_VIEW, label: 'Usar o assistente de IA' },
      { value: Permission.ASSISTANT_CONFIGURE, label: 'Configurar integração de IA' },
    ],
  },
]
