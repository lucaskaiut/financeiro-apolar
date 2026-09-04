import type { PayableAccount } from '../services/reports.service'

export function isPayablesReportOverdue(account: PayableAccount, selectedIds: ReadonlySet<string>): boolean {
  return !selectedIds.has(account.id) && (account.is_overdue || account.is_due_today)
}
