import { http } from '@/shared/api/http'
import type { ApiResponse, ListParams, PaginatedResponse } from '@/shared/types/api'
import type { CostCenter } from '@/shared/types/models'

export interface CostCenterPayload {
  name: string
  bank?: string | null
  agency?: string | null
  account?: string | null
  type: string
  initial_balance?: number
  status?: string
}

export const costCentersService = {
  async list(params: ListParams): Promise<PaginatedResponse<CostCenter>> {
    const response = await http.get<PaginatedResponse<CostCenter>>('/cost-centers', { params })

    return response.data
  },

  async get(id: string): Promise<CostCenter> {
    const response = await http.get<ApiResponse<CostCenter>>(`/cost-centers/${id}`)

    return response.data.data
  },

  async create(payload: CostCenterPayload): Promise<CostCenter> {
    const response = await http.post<ApiResponse<CostCenter>>('/cost-centers', payload)

    return response.data.data
  },

  async update(id: string, payload: CostCenterPayload): Promise<CostCenter> {
    const response = await http.put<ApiResponse<CostCenter>>(`/cost-centers/${id}`, payload)

    return response.data.data
  },

  async remove(id: string): Promise<void> {
    await http.delete(`/cost-centers/${id}`)
  },
}
