import { z } from 'zod'

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

    if (!data.category_id) {
      ctx.addIssue({ code: 'custom', path: ['category_id'], message: 'Selecione a categoria' })
    }
  })

export type AccountFormValues = z.infer<typeof accountSchema>
