import { z } from 'zod'

export const allocationLineSchema = z.object({
  category_id: z.string(),
  subcategory_id: z.string(),
  cost_center_id: z.string(),
  value: z.string(),
})

export const emptyAllocationLine = (): AllocationLineValues => ({
  category_id: '',
  subcategory_id: '',
  cost_center_id: '',
  value: '',
})

export const accountSchema = z
  .object({
    type: z.enum(['payable', 'receivable']),
    description: z.string().min(1, 'Informe a descrição'),
    counterparty: z.string(),
    bank_account_id: z.string(),
    credit_card_id: z.string(),
    company_id: z.string(),
    cost_center_id: z.string(),
    category_id: z.string(),
    subcategory_id: z.string(),
    value: z.string().refine((v) => v !== '' && Number(v) > 0, 'Informe um valor maior que zero'),
    due_date: z.string(),
    purchase_date: z.string(),
    expected_date: z.string(),
    paid_date: z.string(),
    observation: z.string(),
    installments: z.boolean(),
    installment_quantity: z.string().refine((v) => Number(v) >= 1 && Number(v) <= 120, 'Informe entre 1 e 120'),
    installment_interval: z.enum(['daily', 'weekly', 'monthly']),
    split: z.boolean(),
    allocations: z.array(allocationLineSchema),
  })
  .superRefine((data, ctx) => {
    const hasCreditCard = Boolean(data.credit_card_id)

    if (hasCreditCard && data.type !== 'payable') {
      ctx.addIssue({ code: 'custom', path: ['type'], message: 'Compras no cartão são contas a pagar' })
    }

    if (hasCreditCard && !data.purchase_date) {
      ctx.addIssue({ code: 'custom', path: ['purchase_date'], message: 'Informe a data da compra' })
    }

    if (!hasCreditCard && !data.due_date) {
      ctx.addIssue({ code: 'custom', path: ['due_date'], message: 'Informe o vencimento' })
    }

    if (!hasCreditCard && !data.bank_account_id) {
      ctx.addIssue({ code: 'custom', path: ['bank_account_id'], message: 'Selecione a conta bancária' })
    }

    if (data.split) {
      const lines = data.allocations.filter((line) => line.category_id || line.value)

      if (lines.length < 2) {
        ctx.addIssue({
          code: 'custom',
          path: ['allocations'],
          message: 'Informe pelo menos duas linhas de rateio.',
        })
      }

      data.allocations.forEach((line, index) => {
        if (!line.category_id && !line.value) return

        if (!line.category_id) {
          ctx.addIssue({
            code: 'custom',
            path: ['allocations', index, 'category_id'],
            message: 'Selecione a categoria',
          })
        }

        if (!line.value || Number(line.value) <= 0) {
          ctx.addIssue({
            code: 'custom',
            path: ['allocations', index, 'value'],
            message: 'Informe um valor maior que zero',
          })
        }
      })

      const sum = lines.reduce((total, line) => total + Number(line.value || 0), 0)
      const value = Number(data.value)

      if (value > 0 && Math.abs(sum - value) >= 0.01) {
        ctx.addIssue({
          code: 'custom',
          path: ['allocations'],
          message: 'A soma do rateio deve ser igual ao valor do lançamento.',
        })
      }
    } else if (!data.category_id) {
      ctx.addIssue({ code: 'custom', path: ['category_id'], message: 'Selecione a categoria' })
    }
  })

export type AccountFormValues = z.infer<typeof accountSchema>
export type AllocationLineValues = z.infer<typeof allocationLineSchema>
