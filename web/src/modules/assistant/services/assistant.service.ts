import { http } from '@/shared/api/http'
import type { ApiResponse, ListParams, PaginatedResponse } from '@/shared/types/api'
import type { AiSettings, Conversation, ConversationSummary } from '@/shared/types/models'

export interface AiSettingsPayload {
  enabled: boolean
  endpoint?: string | null
  api_key?: string | null
  model?: string | null
  temperature?: number | null
  max_tokens?: number | null
  system_prompt?: string | null
}

export interface ConnectionTestResult {
  ok: boolean
  status: string
  message: string
}

export const assistantService = {
  async listConversations(params: ListParams & { search?: string }): Promise<PaginatedResponse<ConversationSummary>> {
    const response = await http.get<PaginatedResponse<ConversationSummary>>('/assistant/conversations', { params })

    return response.data
  },

  async createConversation(): Promise<Conversation> {
    const response = await http.post<ApiResponse<Conversation>>('/assistant/conversations')

    return response.data.data
  },

  async getConversation(id: string): Promise<Conversation> {
    const response = await http.get<ApiResponse<Conversation>>(`/assistant/conversations/${id}`)

    return response.data.data
  },

  async renameConversation(id: string, title: string): Promise<Conversation> {
    const response = await http.patch<ApiResponse<Conversation>>(`/assistant/conversations/${id}`, { title })

    return response.data.data
  },

  async deleteConversation(id: string): Promise<void> {
    await http.delete(`/assistant/conversations/${id}`)
  },

  async suggestions(): Promise<string[]> {
    const response = await http.get<ApiResponse<string[]>>('/assistant/suggestions')

    return response.data.data
  },

  async getSettings(): Promise<AiSettings> {
    const response = await http.get<ApiResponse<AiSettings>>('/assistant/settings')

    return response.data.data
  },

  async updateSettings(payload: AiSettingsPayload): Promise<AiSettings> {
    const response = await http.put<ApiResponse<AiSettings>>('/assistant/settings', payload)

    return response.data.data
  },

  async testConnection(payload: { endpoint?: string; api_key?: string; model?: string }): Promise<ConnectionTestResult> {
    const response = await http.post<ApiResponse<ConnectionTestResult>>('/assistant/settings/test', payload)

    return response.data.data
  },
}
