import { http } from '@/shared/api/http'
import type { ApiResponse, PaginatedResponse } from '@/shared/types/api'
import type { Account, AccountDocument } from '@/shared/types/models'
import type { AccountListParams } from '@/shared/constants/query-keys'

export interface AccountPayload {
  type: 'payable' | 'receivable'
  description: string
  counterparty?: string | null
  cost_center_id: string
  category_id: string
  subcategory_id?: string | null
  value: number
  due_date: string
  expected_date?: string | null
  paid_date?: string | null
  observation?: string | null
  installments?: { quantity: number; interval?: 'daily' | 'weekly' | 'monthly' } | null
}

export interface SettlePayload {
  value?: number | null
  settled_at?: string | null
  method?: string | null
}

export const accountsService = {
  async list(params: AccountListParams): Promise<PaginatedResponse<Account>> {
    const response = await http.get<PaginatedResponse<Account>>('/accounts', { params })

    return response.data
  },

  async get(id: string): Promise<Account> {
    const response = await http.get<ApiResponse<Account>>(`/accounts/${id}`)

    return response.data.data
  },

  async create(payload: AccountPayload): Promise<ApiResponse<Account[]>> {
    const response = await http.post<ApiResponse<Account[]>>('/accounts', payload)

    return response.data
  },

  async importXlsx(file: File, cost_center_id: string): Promise<void> {
    const formData = new FormData()
    formData.append('file', file)
    formData.append('cost_center_id', cost_center_id)

    await http.post('/accounts/import', formData)
  },

  async update(id: string, payload: Partial<AccountPayload>): Promise<Account> {
    const response = await http.put<ApiResponse<Account>>(`/accounts/${id}`, payload)

    return response.data.data
  },

  async remove(id: string): Promise<void> {
    await http.delete(`/accounts/${id}`)
  },

  async settle(id: string, payload: SettlePayload): Promise<Account> {
    const response = await http.post<ApiResponse<Account>>(`/accounts/${id}/settle`, payload)

    return response.data.data
  },

  async unsettle(id: string, settlementId: string): Promise<Account> {
    const response = await http.delete<ApiResponse<Account>>(`/accounts/${id}/settlements/${settlementId}`)

    return response.data.data
  },

  async reopen(id: string): Promise<Account> {
    const response = await http.post<ApiResponse<Account>>(`/accounts/${id}/reopen`)

    return response.data.data
  },

  async cancel(id: string): Promise<Account> {
    const response = await http.post<ApiResponse<Account>>(`/accounts/${id}/cancel`)

    return response.data.data
  },

  async listDocuments(id: string): Promise<AccountDocument[]> {
    const response = await http.get<ApiResponse<AccountDocument[]>>(`/accounts/${id}/documents`)

    return response.data.data
  },

  async uploadDocuments(id: string, files: File[]): Promise<AccountDocument[]> {
    const formData = new FormData()
    files.forEach((file) => formData.append('files[]', file))

    const response = await http.post<ApiResponse<AccountDocument[]>>(`/accounts/${id}/documents`, formData)

    return response.data.data
  },

  async removeDocument(id: string, documentId: string): Promise<void> {
    await http.delete(`/accounts/${id}/documents/${documentId}`)
  },
}
