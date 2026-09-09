import { BankAccountFilter } from './BankAccountFilter'
import { CostCenterFilter } from './CostCenterFilter'

interface ReportScopeFiltersProps {
  bankAccountId: string
  costCenterId: string
  onBankAccountChange: (value: string) => void
  onCostCenterChange: (value: string) => void
  showBankAccount?: boolean
  showCostCenter?: boolean
}

export function ReportScopeFilters({
  bankAccountId,
  costCenterId,
  onBankAccountChange,
  onCostCenterChange,
  showBankAccount = true,
  showCostCenter = true,
}: ReportScopeFiltersProps) {
  return (
    <>
      {showBankAccount && <BankAccountFilter value={bankAccountId} onChange={onBankAccountChange} />}
      {showCostCenter && <CostCenterFilter value={costCenterId} onChange={onCostCenterChange} />}
    </>
  )
}

export function buildReportScopeSubtitle(
  parts: Array<string | null | undefined>,
): string {
  return parts.filter(Boolean).join(' · ')
}
