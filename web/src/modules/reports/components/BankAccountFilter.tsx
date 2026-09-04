import { Select } from '@/shared/design-system'
import { useBankAccountOptions } from '@/modules/bank-accounts/hooks/useBankAccounts'

interface BankAccountFilterProps {
  value: string
  onChange: (value: string) => void
  className?: string
}

export function BankAccountFilter({ value, onChange, className = 'w-52' }: BankAccountFilterProps) {
  const bankAccounts = useBankAccountOptions()

  return (
    <Select
      aria-label="Conta bancária"
      className={className}
      value={value}
      onChange={(event) => onChange(event.target.value)}
      options={[{ value: '', label: 'Todas as contas' }, ...(bankAccounts.data ?? [])]}
    />
  )
}

export function useBankAccountLabel(bankAccountId: string): string {
  const bankAccounts = useBankAccountOptions()

  return bankAccountId
    ? (bankAccounts.data?.find((option) => option.value === bankAccountId)?.label ?? 'Conta bancária')
    : 'Todas as contas'
}
