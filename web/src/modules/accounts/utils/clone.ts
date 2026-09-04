import type { Account } from '@/shared/types/models'
import type { AccountFormValues } from '../schemas/account.schema'

export function accountToCloneFormValues(account: Account): Partial<AccountFormValues> {
  return {
    type: account.type,
    description: account.description,
    counterparty: account.counterparty ?? '',
    cost_center_id: account.cost_center_id ?? '',
    category_id: account.category_id ?? '',
    subcategory_id: account.subcategory_id ?? '',
    value: String(account.value),
    due_date: account.due_date ?? '',
    expected_date: account.expected_date ?? '',
    paid_date: '',
    observation: account.observation ?? '',
    installments: false,
    installment_quantity: '2',
    installment_interval: 'monthly',
  }
}
