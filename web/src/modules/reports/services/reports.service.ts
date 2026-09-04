import { http } from '@/shared/api/http'
import type { ApiResponse } from '@/shared/types/api'
import { downloadBlob } from '@/shared/utils/report-export'

export interface DailyReport {
  date: string
  payments: Array<{ description: string; bank_account: string | null; category: string | null; value: number }>
  receipts: Array<{ description: string; bank_account: string | null; category: string | null; value: number }>
  groups: DailyBankAccountGroup[]
  total_paid: number
  total_received: number
  balance: number
}

export interface DailyBankAccountGroup {
  bank_account: string
  payments: DailyReport['payments']
  receipts: DailyReport['receipts']
  total_paid: number
  total_received: number
  balance: number
}

export interface WeeklyReport {
  from: string
  to: string
  groups: WeeklyBankAccountGroup[]
  total_paid: number
  total_received: number
  net_balance: number
}

export interface WeeklyBankAccountGroup {
  bank_account: string
  total_paid: number
  total_received: number
  net_balance: number
}

export interface CategoryBankAccountGroup {
  bank_account: string
  expense: Array<{ category: string; total: number }>
  total_expense: number
}

export interface CategoryMatrixColumn {
  key: string
  label: string
}

export interface CategoryMatrixTotals {
  amounts: Record<string, number | null>
  total: number
}

export interface CategoryMatrixAccountRow {
  label: string
  amounts: Record<string, number | null>
  total: number
}

export interface CategoryMatrixSubcategoryGroup {
  subcategory: string
  subtotal: CategoryMatrixTotals
  rows: CategoryMatrixAccountRow[]
}

export interface CategoryMatrixCategoryGroup {
  category: string
  subtotal: CategoryMatrixTotals
  direct_rows: CategoryMatrixAccountRow[]
  subcategories: CategoryMatrixSubcategoryGroup[]
}

export interface CategoryMatrixBankAccountGroup {
  bank_account: string
  categories: CategoryMatrixCategoryGroup[]
  subtotal: CategoryMatrixTotals
}

export interface CategoryMatrix {
  columns: CategoryMatrixColumn[]
  groups: CategoryMatrixBankAccountGroup[]
  grand_total: CategoryMatrixTotals
}

export interface MonthlySummaryReport {
  from: string
  to: string
  columns: CategoryMatrixColumn[]
  rows: MonthlySummaryRow[]
  grand_total: CategoryMatrixTotals
  monthly_average: number
}

export interface MonthlySummaryRow {
  bank_account_id: string | null
  bank_account: string
  amounts: Record<string, number | null>
  total: number
}

export interface CategoryReport {
  from: string
  to: string
  expense: Array<{ category: string; total: number }>
  groups: CategoryBankAccountGroup[]
  matrix: CategoryMatrix
}

export interface BankAccountReportRow {
  bank_account_id: string
  bank_account: string
  initial_balance: number
  income: number
  expense: number
  balance: number
}

export interface CashFlowStatement {
  realized: { from: string; to: string; opening_balance: number; total_in: number; total_out: number; final_balance: number }
  projected: { days: number; opening_balance: number; total_in: number; total_out: number }
  comparative: { realized_net: number; projected_net: number; expected_final_balance: number }
  groups: CashFlowBankAccountGroup[]
}

export interface CashFlowBankAccountGroup {
  bank_account: string
  realized_net: number
  projected_net: number
  expected_final_balance: number
  total_in: number
  total_out: number
}

export interface PayableAccount {
  id: string
  description: string
  counterparty: string | null
  bank_account_id: string | null
  bank_account: string | null
  category: string | null
  value: number
  remaining_amount: number
  due_date: string
  installment: string | null
  status: string
  is_overdue: boolean
  is_due_today: boolean
}

export interface PayablesAccountSection {
  accounts: PayableAccount[]
  total: number
}

export interface PayablesListingGroup {
  bank_account: string
  bank_account_id: string | null
  accounts: PayableAccount[]
  total_open: number
  total_overdue: number
}

export interface PayablesExportGroup {
  bank_account: string
  bank_account_id: string | null
  overdue: PayablesAccountSection
  due_today: PayablesAccountSection
  total_overdue: number
  total_paid_today: number
}

export interface PayablesSummarySection {
  title: string
  rows: Array<{ bank_account: string; amount: number }>
  total: number
}

export interface PayablesExportReport {
  reference_date: string
  from: string
  to: string
  groups: PayablesExportGroup[]
  summary: {
    reference_date: string
    paid_today: PayablesSummarySection
    overdue: PayablesSummarySection
  }
  total_overdue: number
  total_paid_today: number
}

export interface PayablesReport {
  reference_date: string
  from: string
  to: string
  bank_account_id: string | null
  bank_account: string | null
  accounts: PayableAccount[]
  groups: PayablesListingGroup[]
  total_open: number
  total_overdue: number
  count: number
}

export interface ProvisionReportColumn {
  key: string
  label: string
}

export interface ProvisionReportRow {
  account_id: string
  description: string
  due_date: string
  amounts: Record<string, number | null>
  total: number
}

export interface ProvisionReportTotals {
  amounts: Record<string, number | null>
  total: number
}

export interface ProvisionBankAccountGroup {
  bank_account_id: string | null
  bank_account: string
  rows: ProvisionReportRow[]
  subtotal: ProvisionReportTotals
}

export interface ProvisionRawRow {
  bank_account_id: string | null
  bank_account_name: string
  account_id: string
  account_description: string
  due_date: string
  amount: number
}

export interface ProvisionReport {
  from: string
  to: string
  columns: ProvisionReportColumn[]
  rows: ProvisionRawRow[]
  groups: ProvisionBankAccountGroup[]
  grand_total: ProvisionReportTotals
  total_in: number
  total_out: number
}

type ReportExportParams = Record<string, string | number | undefined>

async function fetchExport(path: string, params: ReportExportParams, filename: string): Promise<void> {
  const response = await http.get<Blob>(path, {
    params,
    responseType: 'blob',
  })

  downloadBlob(response.data, filename)
}

export const reportsService = {
  async daily(params: { date?: string; bank_account_id?: string }): Promise<DailyReport> {
    const response = await http.get<ApiResponse<DailyReport>>('/reports/daily', { params })
    return response.data.data
  },

  async dailyExport(params: { date?: string; bank_account_id?: string }): Promise<void> {
    await fetchExport('/reports/daily/export', params, 'relatorio-diario.xlsx')
  },

  async weekly(params: { from?: string; to?: string; bank_account_id?: string }): Promise<WeeklyReport> {
    const response = await http.get<ApiResponse<WeeklyReport>>('/reports/weekly', { params })
    return response.data.data
  },

  async weeklyExport(params: { from?: string; to?: string; bank_account_id?: string }): Promise<void> {
    await fetchExport('/reports/weekly/export', params, 'relatorio-semanal.xlsx')
  },

  async provision(params: { from?: string; to?: string; days?: number; bank_account_id?: string }): Promise<ProvisionReport> {
    const response = await http.get<ApiResponse<ProvisionReport>>('/reports/provision', { params })
    return response.data.data
  },

  async provisionExport(params: { from?: string; to?: string; days?: number; bank_account_id?: string }): Promise<void> {
    await fetchExport('/reports/provision/export', params, 'relatorio-provisao.xlsx')
  },

  async byCategory(params: { from?: string; to?: string; bank_account_id?: string }): Promise<CategoryReport> {
    const response = await http.get<ApiResponse<CategoryReport>>('/reports/by-category', { params })
    return response.data.data
  },

  async byCategoryExport(params: { from?: string; to?: string; bank_account_id?: string }): Promise<void> {
    await fetchExport('/reports/by-category/export', params, 'relatorio-por-categoria.xlsx')
  },

  async monthlySummary(params: { from?: string; to?: string; bank_account_id?: string }): Promise<MonthlySummaryReport> {
    const response = await http.get<ApiResponse<MonthlySummaryReport>>('/reports/monthly-summary', { params })
    return response.data.data
  },

  async monthlySummaryExport(params: { from?: string; to?: string; bank_account_id?: string }): Promise<void> {
    await fetchExport('/reports/monthly-summary/export', params, 'relatorio-resumo-mensal.xlsx')
  },

  async byBankAccount(params?: { bank_account_id?: string }): Promise<{ rows: BankAccountReportRow[] }> {
    const response = await http.get<ApiResponse<{ rows: BankAccountReportRow[] }>>('/reports/by-bank-account', { params })
    return response.data.data
  },

  async byBankAccountExport(params?: { bank_account_id?: string }): Promise<void> {
    await fetchExport('/reports/by-bank-account/export', params ?? {}, 'relatorio-por-centro-de-custo.xlsx')
  },

  async cashFlow(params: { from?: string; to?: string; days?: number; bank_account_id?: string }): Promise<CashFlowStatement> {
    const response = await http.get<ApiResponse<CashFlowStatement>>('/reports/cash-flow', { params })
    return response.data.data
  },

  async cashFlowExport(params: { from?: string; to?: string; days?: number; bank_account_id?: string }): Promise<void> {
    await fetchExport('/reports/cash-flow/export', params, 'demonstrativo-fluxo-caixa.xlsx')
  },

  async payables(params: { from?: string; to?: string; bank_account_id?: string }): Promise<PayablesReport> {
    const response = await http.get<ApiResponse<PayablesReport>>('/reports/payables', { params })
    return response.data.data
  },

  async payablesExport(params: {
    from?: string
    to?: string
    bank_account_id?: string
    selected_ids?: string
  }): Promise<void> {
    await fetchExport('/reports/payables/export', params, 'contas-a-pagar.xlsx')
  },
}
