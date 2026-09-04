import { z } from 'zod'

export const transferSchema = z
  .object({
    from_cost_center_id: z.string().min(1, 'Selecione a conta de origem'),
    to_cost_center_id: z.string().min(1, 'Selecione a conta de destino'),
    value: z.string().refine((v) => v !== '' && Number(v) > 0, 'Informe um valor maior que zero'),
    date: z.string().min(1, 'Informe a data'),
    description: z.string(),
  })
  .refine((data) => data.from_cost_center_id !== data.to_cost_center_id, {
    path: ['to_cost_center_id'],
    message: 'As contas de origem e destino devem ser diferentes',
  })

export type TransferFormValues = z.infer<typeof transferSchema>
