import { http } from '@/shared/api/http'
import type { ApiResponse, ListParams, PaginatedResponse } from '@/shared/types/api'
import type { Company } from '@/shared/types/models'

export interface CompanyPayload {
  name: string
  status?: string
}

export const companiesService = {
  async list(params: ListParams): Promise<PaginatedResponse<Company>> {
    const response = await http.get<PaginatedResponse<Company>>('/companies', { params })

    return response.data
  },

  async get(id: string): Promise<Company> {
    const response = await http.get<ApiResponse<Company>>(`/companies/${id}`)

    return response.data.data
  },

  async create(payload: CompanyPayload): Promise<Company> {
    const response = await http.post<ApiResponse<Company>>('/companies', payload)

    return response.data.data
  },

  async update(id: string, payload: CompanyPayload): Promise<Company> {
    const response = await http.put<ApiResponse<Company>>(`/companies/${id}`, payload)

    return response.data.data
  },

  async remove(id: string): Promise<void> {
    await http.delete(`/companies/${id}`)
  },
}
