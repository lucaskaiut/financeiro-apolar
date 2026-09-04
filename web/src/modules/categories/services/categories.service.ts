import { http } from '@/shared/api/http'
import type { ApiResponse, ListParams, PaginatedResponse } from '@/shared/types/api'
import type { Category } from '@/shared/types/models'

export interface CategoryPayload {
  name: string
  type: 'income' | 'expense'
  color?: string | null
  status?: string
  parent_id?: string | null
}

export const categoriesService = {
  async list(params: ListParams & { type?: string; parent?: string }): Promise<PaginatedResponse<Category>> {
    const response = await http.get<PaginatedResponse<Category>>('/categories', { params })

    return response.data
  },

  async get(id: string): Promise<Category> {
    const response = await http.get<ApiResponse<Category>>(`/categories/${id}`)

    return response.data.data
  },

  async create(payload: CategoryPayload): Promise<Category> {
    const response = await http.post<ApiResponse<Category>>('/categories', payload)

    return response.data.data
  },

  async update(id: string, payload: CategoryPayload): Promise<Category> {
    const response = await http.put<ApiResponse<Category>>(`/categories/${id}`, payload)

    return response.data.data
  },

  async remove(id: string): Promise<void> {
    await http.delete(`/categories/${id}`)
  },
}
