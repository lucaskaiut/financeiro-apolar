import { http } from '@/shared/api/http'
import type { ApiResponse, ListParams, PaginatedResponse } from '@/shared/types/api'
import type { Transfer } from '@/shared/types/models'

export interface TransferPayload {
  from_cost_center_id: string
  to_cost_center_id: string
  value: number
  date: string
  description?: string | null
}

export const transfersService = {
  async list(params: ListParams): Promise<PaginatedResponse<Transfer>> {
    const response = await http.get<PaginatedResponse<Transfer>>('/transfers', { params })

    return response.data
  },

  async create(payload: TransferPayload): Promise<Transfer> {
    const response = await http.post<ApiResponse<Transfer>>('/transfers', payload)

    return response.data.data
  },

  async remove(id: string): Promise<void> {
    await http.delete(`/transfers/${id}`)
  },
}
