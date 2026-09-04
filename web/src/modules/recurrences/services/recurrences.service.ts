import { http } from '@/shared/api/http'
import type { ApiResponse, ListParams, PaginatedResponse } from '@/shared/types/api'
import type { Recurrence } from '@/shared/types/models'

export interface RecurrencePayload {
  type: 'payable' | 'receivable'
  description: string
  counterparty?: string | null
  cost_center_id: string
  category_id: string
  subcategory_id?: string | null
  value: number
  frequency: string
  start_date: string
  end_date?: string | null
  max_occurrences?: number | null
  day_of_month?: number | null
  status?: string
  scope?: 'all' | 'future' | 'current'
}

export const recurrencesService = {
  async list(params: ListParams): Promise<PaginatedResponse<Recurrence>> {
    const response = await http.get<PaginatedResponse<Recurrence>>('/recurrences', { params })

    return response.data
  },

  async get(id: string): Promise<Recurrence> {
    const response = await http.get<ApiResponse<Recurrence>>(`/recurrences/${id}`)

    return response.data.data
  },

  async create(payload: RecurrencePayload): Promise<Recurrence> {
    const response = await http.post<ApiResponse<Recurrence>>('/recurrences', payload)

    return response.data.data
  },

  async update(id: string, payload: RecurrencePayload): Promise<Recurrence> {
    const response = await http.put<ApiResponse<Recurrence>>(`/recurrences/${id}`, payload)

    return response.data.data
  },

  async remove(id: string): Promise<void> {
    await http.delete(`/recurrences/${id}`)
  },
}
