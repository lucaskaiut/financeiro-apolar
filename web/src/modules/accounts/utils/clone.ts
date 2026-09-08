import type { Account } from '@/shared/types/models'
import { type AccountFormValues } from '../schemas/account.schema'

export function accountToCloneFormValues(account: Account): Partial<AccountFormValues> {
  return {
    type: account.type,
    description: account.description,
    counterparty: account.counterparty ?? '',
    bank_account_id: account.bank_account_id ?? '',
    credit_card_id: account.credit_card_id ?? '',
    company_id: account.company_id ?? '',
    cost_center_id: account.cost_center_id ?? '',
    category_id: account.category_id ?? '',
    subcategory_id: account.subcategory_id ?? '',
    value: String(account.value),
    due_date: account.due_date ?? '',
    purchase_date: account.purchase_date ?? '',
    expected_date: account.expected_date ?? '',
    paid_date: '',
    observation: account.observation ?? '',
    installments: false,
    installment_quantity: '2',
    installment_interval: 'monthly',
    split: account.allocation_mode === 'split',
    allocations:
      account.allocation_mode === 'split' && account.allocations && account.allocations.length > 0
        ? account.allocations.map((line) => ({
            category_id: line.category_id ?? '',
            subcategory_id: line.subcategory_id ?? '',
            cost_center_id: line.cost_center_id ?? '',
            value: String(line.value),
          }))
        : [],
  }
}
