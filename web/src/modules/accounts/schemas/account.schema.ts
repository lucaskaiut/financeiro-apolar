import { z } from 'zod'

export const accountSchema = z.object({
  type: z.enum(['payable', 'receivable']),
  description: z.string().min(1, 'Informe a descrição'),
  counterparty: z.string(),
  cost_center_id: z.string().min(1, 'Selecione o centro de custo'),
  category_id: z.string().min(1, 'Selecione a categoria'),
  subcategory_id: z.string(),
  value: z.string().refine((v) => v !== '' && Number(v) > 0, 'Informe um valor maior que zero'),
  due_date: z.string().min(1, 'Informe o vencimento'),
  expected_date: z.string(),
  paid_date: z.string(),
  observation: z.string(),
  installments: z.boolean(),
  installment_quantity: z.string().refine((v) => Number(v) >= 1 && Number(v) <= 120, 'Informe entre 1 e 120'),
  installment_interval: z.enum(['daily', 'weekly', 'monthly']),
})

export type AccountFormValues = z.infer<typeof accountSchema>
