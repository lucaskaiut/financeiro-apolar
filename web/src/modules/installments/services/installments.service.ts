import { http } from '@/shared/api/http'
import type { ApiResponse, ListParams, PaginatedResponse } from '@/shared/types/api'
import type { InstallmentDetail, InstallmentGroup } from '@/shared/types/models'

export interface InstallmentPayload {
  type?: 'payable' | 'receivable'
  description?: string
  counterparty?: string | null
  bank_account_id?: string | null
  company_id?: string | null
  cost_center_id?: string | null
  category_id?: string | null
  subcategory_id?: string | null
  value?: number
  expected_date?: string | null
  observation?: string | null
  scope: 'all' | 'future'
}

export const installmentsService = {
  async list(params: ListParams): Promise<PaginatedResponse<InstallmentGroup>> {
    const response = await http.get<PaginatedResponse<InstallmentGroup>>('/installments', { params })

    return response.data
  },

  async get(id: string): Promise<InstallmentDetail> {
    const response = await http.get<ApiResponse<InstallmentDetail>>(`/installments/${id}`)

    return response.data.data
  },

  async update(id: string, payload: InstallmentPayload): Promise<InstallmentDetail> {
    const response = await http.put<ApiResponse<InstallmentDetail>>(`/installments/${id}`, payload)

    return response.data.data
  },
}
