import { uid, type PurchaseDraft, type SplitDraft } from '../types/import-draft'

export interface InvoiceImportDraftProgress {
  classified: number
  pending: number
  ignored: number
  total: number
}

export interface InvoiceImportDraftSummary {
  id: string
  reference_month: string
  step: number
  source_filename: string | null
  progress: InvoiceImportDraftProgress
  updated_at: string | null
}

export interface InvoiceImportDraft extends InvoiceImportDraftSummary {
  credit_card_id: string
  paid_date: string | null
  bank_account_id: string | null
  category_id: string | null
  cost_center_id: string | null
  due_date: string | null
  items: PurchaseDraft[]
}

export interface SaveInvoiceImportDraftPayload {
  reference_month: string
  paid_date?: string | null
  bank_account_id?: string | null
  category_id?: string | null
  cost_center_id?: string | null
  due_date?: string | null
  step?: number
  source_filename?: string | null
  items: PurchaseDraft[]
}

function mapSplit(split: Record<string, unknown>): SplitDraft {
  return {
    id: typeof split.id === 'string' && split.id ? split.id : uid('s'),
    description: String(split.description ?? ''),
    value: Number(split.value ?? 0),
    category_id: String(split.category_id ?? ''),
    subcategory_id: String(split.subcategory_id ?? ''),
    cost_center_id: String(split.cost_center_id ?? ''),
  }
}

export function mapDraftItems(items: unknown[]): PurchaseDraft[] {
  return items.map((raw) => {
    const item = raw as Record<string, unknown>
    const splits = Array.isArray(item.splits) ? item.splits.map((split) => mapSplit(split as Record<string, unknown>)) : []
    const existing = item.existing as Record<string, unknown> | null | undefined

    return {
      id: typeof item.id === 'string' && item.id ? item.id : uid('p'),
      purchase_date: String(item.purchase_date ?? ''),
      description: String(item.description ?? ''),
      value: Number(item.value ?? 0),
      category_id: String(item.category_id ?? ''),
      subcategory_id: String(item.subcategory_id ?? ''),
      cost_center_id: String(item.cost_center_id ?? ''),
      status: item.status === 'ignored' ? 'ignored' : 'normal',
      is_duplicate: Boolean(item.is_duplicate),
      existing: existing
        ? {
            date: existing.date ? String(existing.date) : null,
            value: Number(existing.value ?? 0),
            description: String(existing.description ?? ''),
          }
        : null,
      splits,
    }
  })
}

export function buildSaveDraftPayload(options: {
  referenceMonth: string
  paidDate: string
  bankAccountId: string
  categoryId: string
  costCenterId: string
  dueDate: string
  step: number
  sourceFilename: string | null
  items: PurchaseDraft[]
}): SaveInvoiceImportDraftPayload {
  return {
    reference_month: options.referenceMonth,
    paid_date: options.paidDate || null,
    bank_account_id: options.bankAccountId || null,
    category_id: options.categoryId || null,
    cost_center_id: options.costCenterId || null,
    due_date: options.dueDate || null,
    step: options.step,
    source_filename: options.sourceFilename,
    items: options.items,
  }
}
