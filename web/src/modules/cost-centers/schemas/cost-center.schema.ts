import { z } from 'zod'

export const costCenterSchema = z.object({
  name: z.string().min(1, 'Informe o nome'),
  bank: z.string(),
  agency: z.string(),
  account: z.string(),
  type: z.string().min(1, 'Selecione o tipo'),
  initial_balance: z.string().refine((v) => v === '' || (!Number.isNaN(Number(v)) && Number(v) >= 0), 'Informe um valor válido'),
  status: z.string().min(1),
})

export type CostCenterFormValues = z.infer<typeof costCenterSchema>
