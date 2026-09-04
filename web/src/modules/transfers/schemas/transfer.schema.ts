import { z } from 'zod'

export const transferSchema = z
  .object({
    from_bank_account_id: z.string().min(1, 'Selecione a conta de origem'),
    to_bank_account_id: z.string().min(1, 'Selecione a conta de destino'),
    value: z.string().refine((v) => v !== '' && Number(v) > 0, 'Informe um valor maior que zero'),
    date: z.string().min(1, 'Informe a data'),
    description: z.string(),
  })
  .refine((data) => data.from_bank_account_id !== data.to_bank_account_id, {
    path: ['to_bank_account_id'],
    message: 'As contas de origem e destino devem ser diferentes',
  })

export type TransferFormValues = z.infer<typeof transferSchema>
