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

export interface CostCenterBalance {
  cost_center_id: string
  cost_center: string
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
  cost_center: string | null
  category: string | null
  value: number
  remaining_amount: number
  due_date: string
}

export interface DashboardSummary {
  cost_centers: Array<{ id: string; name: string }>
  selected_cost_center_id: string | null
  kpis: DashboardKpis
  cash_flow_series: CashFlowMonth[]
  projected_series: ProjectedDay[]
  expense_by_category: CategoryTotal[]
  income_by_category: CategoryTotal[]
  balance_by_cost_center: CostCenterBalance[]
  overdue: DashboardAccount[]
  upcoming: DashboardAccount[]
  payables_next_7d: DashboardAccount[]
  payables_next_7d_total: number
}

export const dashboardService = {
  async summary(cost_center_id?: string): Promise<DashboardSummary> {
    const response = await http.get<ApiResponse<DashboardSummary>>('/dashboard', {
      params: cost_center_id ? { cost_center_id } : undefined,
    })

    return response.data.data
  },
}
