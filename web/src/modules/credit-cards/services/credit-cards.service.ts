import { http } from '@/shared/api/http'
import type { ApiResponse, ListParams, PaginatedResponse } from '@/shared/types/api'
import type { CreditCard, CreditCardInvoice } from '@/shared/types/models'

export interface CreditCardPayload {
  name: string
  institution?: string | null
  limit?: number | null
  closing_day?: number | null
  due_day?: number | null
  bank_account_id?: string | null
  status?: string
}

export const creditCardsService = {
  async list(params: ListParams): Promise<PaginatedResponse<CreditCard>> {
    const response = await http.get<PaginatedResponse<CreditCard>>('/credit-cards', { params })

    return response.data
  },

  async get(id: string): Promise<CreditCard> {
    const response = await http.get<ApiResponse<CreditCard>>(`/credit-cards/${id}`)

    return response.data.data
  },

  async create(payload: CreditCardPayload): Promise<CreditCard> {
    const response = await http.post<ApiResponse<CreditCard>>('/credit-cards', payload)

    return response.data.data
  },

  async update(id: string, payload: CreditCardPayload): Promise<CreditCard> {
    const response = await http.put<ApiResponse<CreditCard>>(`/credit-cards/${id}`, payload)

    return response.data.data
  },

  async remove(id: string): Promise<void> {
    await http.delete(`/credit-cards/${id}`)
  },

  async listInvoices(id: string): Promise<CreditCardInvoice[]> {
    const response = await http.get<ApiResponse<CreditCardInvoice[]>>(`/credit-cards/${id}/invoices`)

    return response.data.data
  },

  async closeInvoice(id: string, referenceMonth: string): Promise<CreditCardInvoice> {
    const response = await http.post<ApiResponse<CreditCardInvoice>>(`/credit-cards/${id}/invoices/close`, {
      reference_month: referenceMonth,
    })

    return response.data.data
  },
}
