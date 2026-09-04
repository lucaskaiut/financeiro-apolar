import { http } from '@/shared/api/http'
import type { ApiResponse, PaginatedResponse } from '@/shared/types/api'
import type { Account, BankTransaction } from '@/shared/types/models'

export interface ReconciliationListParams {
  page?: number
  per_page?: number
  status?: string
  cost_center_id?: string
}

export const reconciliationService = {
  async list(params: ReconciliationListParams): Promise<PaginatedResponse<BankTransaction>> {
    const response = await http.get<PaginatedResponse<BankTransaction>>('/reconciliation/transactions', { params })

    return response.data
  },

  async importOfx(cost_center_id: string, content: string): Promise<{ imported: number; skipped: number }> {
    const response = await http.post<ApiResponse<{ imported: number; skipped: number }>>('/reconciliation/import', {
      cost_center_id,
      content,
    })

    return response.data.data
  },

  async auto(from?: string, to?: string): Promise<{ matched: number; ambiguous: number; not_found: number }> {
    const response = await http.post<ApiResponse<{ matched: number; ambiguous: number; not_found: number }>>(
      '/reconciliation/auto',
      { from, to },
    )

    return response.data.data
  },

  async candidates(id: string, from?: string, to?: string): Promise<{ transaction: BankTransaction; candidates: Account[] }> {
    const response = await http.get<ApiResponse<{ transaction: BankTransaction; candidates: Account[] }>>(
      `/reconciliation/transactions/${id}/candidates`,
      { params: { from, to } },
    )

    return response.data.data
  },

  async reconcile(id: string, account_id: string): Promise<BankTransaction> {
    const response = await http.post<ApiResponse<BankTransaction>>(`/reconciliation/transactions/${id}/reconcile`, {
      account_id,
    })

    return response.data.data
  },

  async ignore(id: string): Promise<BankTransaction> {
    const response = await http.post<ApiResponse<BankTransaction>>(`/reconciliation/transactions/${id}/ignore`)

    return response.data.data
  },

  async undo(id: string): Promise<BankTransaction> {
    const response = await http.post<ApiResponse<BankTransaction>>(`/reconciliation/transactions/${id}/undo`)

    return response.data.data
  },

  async createAccount(
    id: string,
    payload: {
      type: 'payable' | 'receivable'
      description: string
      category_id: string
      cost_center_id?: string
      value?: number
      due_date?: string
    },
  ): Promise<Account> {
    const response = await http.post<ApiResponse<Account>>(`/reconciliation/transactions/${id}/create-account`, payload)

    return response.data.data
  },
}
