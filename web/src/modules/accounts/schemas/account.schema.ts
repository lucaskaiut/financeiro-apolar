import { z } from 'zod'

const allocationRowSchema = z.object({
  cost_center_id: z.string(),
  company_id: z.string(),
  category_id: z.string().min(1, 'Informe a categoria'),
  value: z.string(),
  percentage: z.string(),
})

export const accountSchema = z
  .object({
    type: z.enum(['payable', 'receivable']),
    description: z.string().min(1, 'Informe a descrição'),
    counterparty: z.string(),
    bank_account_id: z.string(),
    company_id: z.string(),
    cost_center_id: z.string(),
    category_id: z.string(),
    subcategory_id: z.string(),
    value: z.string().refine((v) => v !== '' && Number(v) > 0, 'Informe um valor maior que zero'),
    due_date: z.string().min(1, 'Informe o vencimento'),
    expected_date: z.string(),
    paid_date: z.string(),
    observation: z.string(),
    use_allocations: z.boolean(),
    allocations: z.array(allocationRowSchema),
    installments: z.boolean(),
    installment_quantity: z.string().refine((v) => Number(v) >= 1 && Number(v) <= 120, 'Informe entre 1 e 120'),
    installment_interval: z.enum(['daily', 'weekly', 'monthly']),
  })
  .superRefine((data, ctx) => {
    if (data.use_allocations) {
      if (data.allocations.length === 0) {
        ctx.addIssue({ code: 'custom', path: ['allocations'], message: 'Adicione ao menos uma linha de rateio' })
      }
      return
    }

    if (!data.bank_account_id) {
      ctx.addIssue({ code: 'custom', path: ['bank_account_id'], message: 'Selecione a conta bancária' })
    }
    if (!data.category_id) {
      ctx.addIssue({ code: 'custom', path: ['category_id'], message: 'Selecione a categoria' })
    }
  })

export type AccountFormValues = z.infer<typeof accountSchema>
