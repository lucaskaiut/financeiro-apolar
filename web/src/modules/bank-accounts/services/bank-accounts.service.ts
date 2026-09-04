import { http } from '@/shared/api/http'
import type { ApiResponse, ListParams, PaginatedResponse } from '@/shared/types/api'
import type { BankAccount } from '@/shared/types/models'

export interface BankAccountPayload {
  name: string
  bank?: string | null
  agency?: string | null
  account?: string | null
  type: string
  initial_balance?: number
  status?: string
}

export const bankAccountsService = {
  async list(params: ListParams): Promise<PaginatedResponse<BankAccount>> {
    const response = await http.get<PaginatedResponse<BankAccount>>('/bank-accounts', { params })

    return response.data
  },

  async get(id: string): Promise<BankAccount> {
    const response = await http.get<ApiResponse<BankAccount>>(`/bank-accounts/${id}`)

    return response.data.data
  },

  async create(payload: BankAccountPayload): Promise<BankAccount> {
    const response = await http.post<ApiResponse<BankAccount>>('/bank-accounts', payload)

    return response.data.data
  },

  async update(id: string, payload: BankAccountPayload): Promise<BankAccount> {
    const response = await http.put<ApiResponse<BankAccount>>(`/bank-accounts/${id}`, payload)

    return response.data.data
  },

  async remove(id: string): Promise<void> {
    await http.delete(`/bank-accounts/${id}`)
  },
}
