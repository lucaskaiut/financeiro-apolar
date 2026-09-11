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

  async importInvoice(
    id: string,
    payload: {
      file: File
      reference_month: string
      paid_date: string
      bank_account_id: string
      category_id: string
      cost_center_id?: string | null
    },
  ): Promise<{ imported: number; skipped: number; total: number; invoice_id: string }> {
    const formData = new FormData()
    formData.append('file', payload.file)
    formData.append('reference_month', payload.reference_month)
    formData.append('paid_date', payload.paid_date)
    formData.append('bank_account_id', payload.bank_account_id)
    formData.append('category_id', payload.category_id)
    if (payload.cost_center_id) formData.append('cost_center_id', payload.cost_center_id)

    const response = await http.post<ApiResponse<{ imported: number; skipped: number; total: number; invoice_id: string }>>(
      `/credit-cards/${id}/invoices/import`,
      formData,
    )

    return response.data.data
  },
}
