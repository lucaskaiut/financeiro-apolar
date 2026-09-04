import type { ListParams } from '@/shared/types/api'

export const queryKeys = {
  session: ['session'] as const,
  dashboard: ['dashboard'] as const,

  users: {
    all: ['users'] as const,
    list: (params: ListParams) => ['users', 'list', params] as const,
    detail: (id: string) => ['users', 'detail', id] as const,
  },

  roles: {
    all: ['roles'] as const,
    list: (params: ListParams) => ['roles', 'list', params] as const,
    detail: (id: number) => ['roles', 'detail', id] as const,
  },

  costCenters: {
    all: ['cost-centers'] as const,
    list: (params: ListParams) => ['cost-centers', 'list', params] as const,
    detail: (id: string) => ['cost-centers', 'detail', id] as const,
  },

  categories: {
    all: ['categories'] as const,
    list: (params: ListParams & { type?: string; parent?: string }) => ['categories', 'list', params] as const,
    detail: (id: string) => ['categories', 'detail', id] as const,
  },

  accounts: {
    all: ['accounts'] as const,
    list: (params: AccountListParams) => ['accounts', 'list', params] as const,
    detail: (id: string) => ['accounts', 'detail', id] as const,
    documents: (id: string) => ['accounts', 'detail', id, 'documents'] as const,
  },

  recurrences: {
    all: ['recurrences'] as const,
    list: (params: ListParams) => ['recurrences', 'list', params] as const,
    detail: (id: string) => ['recurrences', 'detail', id] as const,
  },

  transfers: {
    all: ['transfers'] as const,
    list: (params: ListParams) => ['transfers', 'list', params] as const,
  },

  cashFlow: {
    realized: (params: Record<string, unknown>) => ['cash-flow', 'realized', params] as const,
    projected: (params: Record<string, unknown>) => ['cash-flow', 'projected', params] as const,
  },

  reconciliation: {
    all: ['reconciliation'] as const,
    list: (params: ListParams & { status?: string; cost_center_id?: string }) =>
      ['reconciliation', 'transactions', params] as const,
    candidates: (id: string, from?: string, to?: string) =>
      ['reconciliation', 'candidates', id, { from, to }] as const,
  },

  reports: {
    daily: (params: Record<string, unknown>) => ['reports', 'daily', params] as const,
    weekly: (params: Record<string, unknown>) => ['reports', 'weekly', params] as const,
    provision: (params: Record<string, unknown>) => ['reports', 'provision', params] as const,
    byCategory: (params: Record<string, unknown>) => ['reports', 'by-category', params] as const,
    monthlySummary: (params: Record<string, unknown>) => ['reports', 'monthly-summary', params] as const,
    byCostCenter: (params: Record<string, unknown>) => ['reports', 'by-cost-center', params] as const,
    cashFlow: (params: Record<string, unknown>) => ['reports', 'cash-flow', params] as const,
    payables: (params: Record<string, unknown>) => ['reports', 'payables', params] as const,
  },

  audit: {
    list: (params: ListParams & { action?: string }) => ['audit', 'list', params] as const,
  },

  assistant: {
    all: ['assistant'] as const,
    conversations: (params: ListParams & { search?: string }) =>
      ['assistant', 'conversations', params] as const,
    conversation: (id: string) => ['assistant', 'conversation', id] as const,
    suggestions: ['assistant', 'suggestions'] as const,
    settings: ['assistant', 'settings'] as const,
  },
} as const

export interface AccountListParams extends ListParams {
  type?: string
  status?: string
  overdue?: boolean | string
  cost_center_id?: string
  category_id?: string
  due_from?: string
  due_to?: string
  paid_from?: string
  paid_to?: string
}
