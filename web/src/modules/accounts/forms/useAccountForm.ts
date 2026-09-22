import { useEffect } from 'react'
import { useForm, type UseFormReturn } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { accountSchema, emptyAccountFormValues, type AccountFormValues } from '../schemas/account.schema'
import type { AccountPayload } from '../services/accounts.service'

interface UseAccountFormOptions {
  mode?: 'create' | 'edit'
  defaultValues?: Partial<AccountFormValues>
  isCardPurchase?: boolean
  purchaseDate?: string | null
}

export function useAccountForm({
  mode = 'create',
  defaultValues,
  isCardPurchase = false,
  purchaseDate = null,
}: UseAccountFormOptions = {}): UseFormReturn<AccountFormValues> {
  const form = useForm<AccountFormValues>({
    resolver: zodResolver(accountSchema),
    defaultValues: emptyAccountFormValues({
      ...defaultValues,
      ...(isCardPurchase && purchaseDate ? { purchase_date: purchaseDate } : {}),
    }),
  })

  const creditCardId = form.watch('credit_card_id')

  useEffect(() => {
    if (mode !== 'create' || !creditCardId) return

    form.setValue('type', 'payable')
    form.setValue('bank_account_id', '')
  }, [creditCardId, form, mode])

  return form
}

export function buildAccountPayload(
  values: AccountFormValues,
  { mode, isCardPurchase = false }: { mode: 'create' | 'edit'; isCardPurchase?: boolean },
): AccountPayload {
  const hasCreditCard = Boolean(values.credit_card_id) || isCardPurchase

  const payload: AccountPayload = {
    type: hasCreditCard ? 'payable' : values.type,
    description: values.description,
    counterparty: values.counterparty || null,
    cost_center_id: values.split ? null : values.cost_center_id || null,
    category_id: values.split ? null : values.category_id,
    subcategory_id: values.split ? null : values.subcategory_id || null,
    value: Number(values.value),
    purchase_date: values.purchase_date || null,
    expected_date: values.expected_date || null,
    paid_date: mode === 'edit' ? values.paid_date || null : undefined,
    observation: values.observation || null,
    installments:
      mode === 'create' && values.installments
        ? hasCreditCard
          ? { quantity: Number(values.installment_quantity) }
          : values.customize_installments
            ? {
                quantity: Number(values.installment_quantity),
                interval: values.installment_interval,
                items: values.installment_items.map((line) => ({
                  value: Number(line.value),
                  due_date: line.due_date,
                })),
              }
            : { quantity: Number(values.installment_quantity), interval: values.installment_interval }
        : null,
    allocations: values.split
      ? values.allocations
          .filter((line) => line.category_id && Number(line.value) > 0)
          .map((line) => ({
            category_id: line.category_id,
            subcategory_id: line.subcategory_id || null,
            cost_center_id: line.cost_center_id || null,
            value: Number(line.value),
          }))
      : null,
  }

  if (hasCreditCard) {
    payload.credit_card_id = values.credit_card_id || null
    // Mantém o vencimento já calculado do ciclo; não envia null (evita 422 no update).
    if (values.due_date) {
      payload.due_date = values.due_date
    }
  } else {
    payload.bank_account_id = values.bank_account_id || null
    payload.credit_card_id = null
    payload.due_date = values.due_date
  }

  return payload
}
