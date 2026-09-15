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

export interface ImportPreviewItem {
  id: number
  purchase_date: string
  description: string
  value: number
  category_id: string | null
  subcategory_id: string | null
  cost_center_id: string | null
  status: 'normal' | 'ignored'
  is_duplicate: boolean
  existing: { date: string | null; value: number; description: string } | null
}

export interface ImportPreviewResult {
  due_date: string
  total: number
  items: ImportPreviewItem[]
}

export interface ImportSplitPayload {
  description: string
  value: number
  category_id: string
  subcategory_id?: string | null
  cost_center_id?: string | null
}

export interface ImportItemPayload {
  description: string
  purchase_date: string
  value: number
  category_id?: string | null
  subcategory_id?: string | null
  cost_center_id?: string | null
  status?: 'normal' | 'ignored'
  splits?: ImportSplitPayload[]
}

export interface ImportInvoiceResult {
  imported: number
  ignored: number
  total: number
  invoice_id: string
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

  async previewInvoice(
    id: string,
    payload: {
      file: File
      reference_month: string
      category_id: string
      cost_center_id?: string | null
    },
  ): Promise<ImportPreviewResult> {
    const formData = new FormData()
    formData.append('file', payload.file)
    formData.append('reference_month', payload.reference_month)
    formData.append('category_id', payload.category_id)
    if (payload.cost_center_id) formData.append('cost_center_id', payload.cost_center_id)

    const response = await http.post<ApiResponse<ImportPreviewResult>>(
      `/credit-cards/${id}/invoices/import/preview`,
      formData,
    )

    return response.data.data
  },

  async importInvoice(
    id: string,
    payload: {
      reference_month: string
      paid_date: string
      bank_account_id: string
      items: ImportItemPayload[]
    },
  ): Promise<ImportInvoiceResult> {
    const response = await http.post<ApiResponse<ImportInvoiceResult>>(`/credit-cards/${id}/invoices/import`, payload)

    return response.data.data
  },
}

