import type { Recurrence } from '@/shared/types/models'
import type { RecurrenceFormValues } from '../schemas/recurrence.schema'

export function recurrenceToCloneFormValues(recurrence: Recurrence): Partial<RecurrenceFormValues> {
  return {
    type: recurrence.type as 'payable' | 'receivable',
    description: recurrence.description,
    counterparty: recurrence.counterparty ?? '',
    cost_center_id: recurrence.cost_center_id ?? '',
    category_id: recurrence.category_id ?? '',
    subcategory_id: recurrence.subcategory_id ?? '',
    value: String(recurrence.value),
    frequency: recurrence.frequency,
    start_date: recurrence.start_date,
    end_date: recurrence.end_date ?? '',
    max_occurrences: recurrence.max_occurrences ? String(recurrence.max_occurrences) : '',
    day_of_month: recurrence.day_of_month ? String(recurrence.day_of_month) : '',
    scope: 'all',
  }
}
