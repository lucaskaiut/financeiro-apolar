import { http } from '@/shared/api/http'
import type { ApiResponse } from '@/shared/types/api'

export interface RealizedEntry {
  id: string
  date: string
  description: string
  cost_center: string | null
  category: string | null
  direction: 'in' | 'out'
  value: number
  is_transfer: boolean
}

export interface RealizedCashFlow {
  from: string
  to: string
  opening_balance: number
  total_in: number
  total_out: number
  final_balance: number
  entries: RealizedEntry[]
}

export interface ProjectedItem {
  id: string
  description: string
  counterparty: string | null
  cost_center: string | null
  category: string | null
  direction: 'in' | 'out'
  value: number
  remaining_amount: number
  due_date: string
  installment: string | null
}

export interface ProjectedCashFlow {
  from: string
  to: string
  opening_balance: number
  days: number
  total_in: number
  total_out: number
  series: Array<{ date: string; in: number; out: number; projected_balance: number }>
  accounts: ProjectedItem[]
  installments: ProjectedItem[]
  recurrences: ProjectedItem[]
}

export const cashFlowService = {
  async realized(params: Record<string, string>): Promise<RealizedCashFlow> {
    const response = await http.get<ApiResponse<RealizedCashFlow>>('/cash-flow/realized', { params })

    return response.data.data
  },

  async projected(params: Record<string, string | number>): Promise<ProjectedCashFlow> {
    const response = await http.get<ApiResponse<ProjectedCashFlow>>('/cash-flow/projected', { params })

    return response.data.data
  },
}
