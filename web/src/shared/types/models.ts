import type { Permission } from '@/shared/constants/permissions'

export interface Role {
  id: number
  name: string
  description: string | null
  permissions?: Permission[]
}

export interface User {
  id: string
  name: string
  email: string
  phone: string | null
  document: string | null
  is_master: boolean
  roles?: Role[]
  created_at: string | null
  updated_at: string | null
}

export interface Tenant {
  id: string
  name: string
  document: string
  email: string
  phone: string | null
  domain: string
  is_umbrella?: boolean
  created_at: string | null
  updated_at: string | null
}

export interface AvailableTenant {
  id: string
  name: string
  is_home?: boolean
  is_umbrella?: boolean
}

export interface Session {
  user: User
  tenant: Tenant
  roles: Role[]
  permissions: Permission[]
  is_master: boolean
  available_tenants: AvailableTenant[]
}

export interface CostCenter {
  id: string
  name: string
  bank: string | null
  agency: string | null
  account: string | null
  type: string
  type_label: string
  initial_balance: number
  status: string
  created_at: string | null
  updated_at: string | null
}

export interface Category {
  id: string
  name: string
  type: 'income' | 'expense'
  type_label: string
  color: string | null
  status: string
  parent_id: string | null
  parent_name?: string | null
  subcategories_count?: number | null
  created_at: string | null
  updated_at: string | null
}

export interface Settlement {
  id: string
  value: number
  settled_at: string | null
  method: string | null
  created_at: string | null
}

export interface AccountDocument {
  id: string
  name: string
  mime_type: string | null
  size: number
  created_at: string | null
}

export interface Account {
  id: string
  type: 'payable' | 'receivable'
  type_label: string
  description: string
  counterparty: string | null
  cost_center_id: string | null
  cost_center: string | null
  category_id: string | null
  category: { name: string; color: string | null; type: string } | null
  subcategory_id: string | null
  subcategory?: { name: string } | null
  value: number
  settled_amount: number
  remaining_amount: number
  due_date: string | null
  expected_date: string | null
  paid_date: string | null
  observation: string | null
  status: 'open' | 'partial' | 'settled' | 'cancelled'
  status_label: string
  installment_group_id: string | null
  installment_number: number | null
  installment_total: number | null
  recurrence_id: string | null
  transfer_id: number | null
  is_reconciled: boolean
  settlements?: Settlement[]
  created_at: string | null
  updated_at: string | null
}

export interface Recurrence {
  id: string
  type: string
  description: string
  counterparty: string | null
  cost_center_id: string | null
  cost_center: string | null
  category_id: string | null
  category: string | null
  subcategory_id: string | null
  subcategory: string | null
  value: number
  frequency: string
  frequency_label: string
  start_date: string
  end_date: string | null
  max_occurrences: number | null
  day_of_month: number | null
  status: string
  occurrences_count: number
  created_at: string | null
  updated_at: string | null
}

export interface Transfer {
  id: string
  from_cost_center_id: string | null
  from_cost_center: string | null
  to_cost_center_id: string | null
  to_cost_center: string | null
  value: number
  date: string
  description: string | null
  created_at: string | null
}

export interface BankTransaction {
  id: string
  cost_center_id: string | null
  cost_center: string | null
  date: string
  value: number
  type: 'credit' | 'debit' | 'other'
  description: string | null
  transaction_id: string | null
  status: 'pending' | 'matched' | 'ignored'
  matched_account?: { uuid: string; description: string } | null
  created_at: string | null
}

export interface AuditLog {
  id: number
  action: string
  entity_type: string | null
  entity_id: string | null
  details: Record<string, unknown> | null
  ip: string | null
  user: { id: string; name: string; email: string } | null
  created_at: string | null
}

export interface ConversationSummary {
  id: string
  title: string
  message_count: number | null
  last_message: string | null
  created_at: string | null
  updated_at: string | null
}

export interface AssistantMessage {
  id: string
  role: 'user' | 'assistant' | 'tool' | 'system'
  content: string
  tool_calls: Array<{ id: string; name: string; arguments: Record<string, unknown> }> | null
  tool_results: Array<{ id: string; name: string }> | null
  created_at: string | null
}

export interface Conversation {
  id: string
  title: string
  messages: AssistantMessage[]
  created_at: string | null
  updated_at: string | null
}

export interface AiSettings {
  enabled: boolean
  endpoint: string | null
  model: string | null
  temperature: number | null
  max_tokens: number | null
  system_prompt: string | null
  has_api_key: boolean
  configured: boolean
}

