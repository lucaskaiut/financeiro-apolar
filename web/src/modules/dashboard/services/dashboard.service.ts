import { http } from '@/shared/api/http'
import type { ApiResponse } from '@/shared/types/api'

export interface DashboardKpis {
  current_balance: number
  month_income: number
  month_expense: number
  month_result: number
  receivable_open: number
  payable_open: number
  overdue_total: number
  overdue_count: number
  projected_7d: number
  projected_balance: number | null
}

export interface CashFlowMonth {
  month: string
  label: string
  income: number
  expense: number
  balance: number
}

export interface ProjectedDay {
  date: string
  in: number
  out: number
  projected_balance: number
}

export interface CategoryTotal {
  category: string
  total: number
}

export interface BankAccountBalance {
  bank_account_id: string
  bank_account: string
  initial_balance: number
  income: number
  expense: number
  balance: number
}

export interface DashboardAccount {
  id: string
  description: string
  counterparty: string | null
  type: 'payable' | 'receivable'
  bank_account: string | null
  category: string | null
  value: number
  remaining_amount: number
  due_date: string
}

export interface DashboardSummary {
  cost_centers: Array<{ id: string; name: string }>
  selected_cost_center_id: string | null
  bank_accounts: Array<{ id: string; name: string }>
  selected_bank_account_id: string | null
  kpis: DashboardKpis
  cash_flow_series: CashFlowMonth[]
  projected_series: ProjectedDay[]
  expense_by_category: CategoryTotal[]
  income_by_category: CategoryTotal[]
  balance_by_bank_account: BankAccountBalance[]
  overdue: DashboardAccount[]
  upcoming: DashboardAccount[]
  payables_next_7d: DashboardAccount[]
  payables_next_7d_total: number
}

export const dashboardService = {
  async summary(cost_center_id?: string, bank_account_id?: string): Promise<DashboardSummary> {
    const params: Record<string, string> = {}
    if (cost_center_id) params.cost_center_id = cost_center_id
    if (bank_account_id) params.bank_account_id = bank_account_id

    const response = await http.get<ApiResponse<DashboardSummary>>('/dashboard', {
      params: Object.keys(params).length > 0 ? params : undefined,
    })

    return response.data.data
  },
}
