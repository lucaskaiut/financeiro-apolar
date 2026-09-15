export interface SplitDraft {
  id: string
  description: string
  value: number
  category_id: string
  subcategory_id: string
  cost_center_id: string
}

export interface PurchaseDraft {
  id: string
  purchase_date: string
  description: string
  value: number
  category_id: string
  subcategory_id: string
  cost_center_id: string
  status: 'normal' | 'ignored'
  is_duplicate: boolean
  existing: { date: string | null; value: number; description: string } | null
  splits: SplitDraft[]
}

let counter = 0

export function uid(prefix = 'd'): string {
  counter += 1

  return `${prefix}-${Date.now().toString(36)}-${counter}`
}

export function round(value: number): number {
  return Math.round(value * 100) / 100
}

export function emptySplit(value = 0): SplitDraft {
  return {
    id: uid('s'),
    description: '',
    value,
    category_id: '',
    subcategory_id: '',
    cost_center_id: '',
  }
}
