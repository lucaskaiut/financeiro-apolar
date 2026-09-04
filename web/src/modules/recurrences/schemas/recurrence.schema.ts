import { z } from 'zod'

export const recurrenceSchema = z.object({
  type: z.enum(['payable', 'receivable']),
  description: z.string().min(1, 'Informe a descrição'),
  counterparty: z.string(),
  cost_center_id: z.string().min(1, 'Selecione o centro de custo'),
  category_id: z.string().min(1, 'Selecione a categoria'),
  subcategory_id: z.string(),
  value: z.string().refine((v) => v !== '' && Number(v) > 0, 'Informe um valor maior que zero'),
  frequency: z.string().min(1, 'Selecione a frequência'),
  start_date: z.string().min(1, 'Informe a data inicial'),
  end_date: z.string(),
  max_occurrences: z.string().refine((v) => v === '' || (Number(v) >= 1 && Number(v) <= 366), 'Informe entre 1 e 366'),
  day_of_month: z.string().refine((v) => v === '' || (Number(v) >= 1 && Number(v) <= 31), 'Informe entre 1 e 31'),
  scope: z.enum(['all', 'future', 'current']),
})

export type RecurrenceFormValues = z.infer<typeof recurrenceSchema>
