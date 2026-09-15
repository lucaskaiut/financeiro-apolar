import { z } from 'zod'

export function createInstallmentSchema(isCardPurchase: boolean) {
  return z
    .object({
      type: z.enum(['payable', 'receivable']),
      description: z.string().min(1, 'Informe a descrição'),
      counterparty: z.string(),
      bank_account_id: z.string(),
      company_id: z.string(),
      cost_center_id: z.string(),
      category_id: z.string(),
      subcategory_id: z.string(),
      value: z.string().optional(),
      expected_date: z.string(),
      observation: z.string(),
      scope: z.enum(['all', 'future']),
    })
    .superRefine((data, ctx) => {
      if (data.scope === 'all' && (!data.value || Number(data.value) <= 0)) {
        ctx.addIssue({ code: 'custom', path: ['value'], message: 'Informe um valor maior que zero' })
      }

      if (!isCardPurchase && !data.bank_account_id) {
        ctx.addIssue({ code: 'custom', path: ['bank_account_id'], message: 'Selecione a conta bancária' })
      }

      if (!data.category_id) {
        ctx.addIssue({ code: 'custom', path: ['category_id'], message: 'Selecione a categoria' })
      }
    })
}

export type InstallmentFormValues = z.infer<ReturnType<typeof createInstallmentSchema>>
