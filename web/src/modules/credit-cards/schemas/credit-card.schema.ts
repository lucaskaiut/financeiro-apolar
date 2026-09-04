import { z } from 'zod'

export const creditCardSchema = z.object({
  name: z.string().min(1, 'Informe o nome'),
  institution: z.string(),
  limit: z.string().refine((v) => v === '' || (!Number.isNaN(Number(v)) && Number(v) >= 0), 'Informe um valor válido'),
  closing_day: z.string().refine((v) => v === '' || (Number(v) >= 1 && Number(v) <= 31), 'Informe entre 1 e 31'),
  due_day: z.string().refine((v) => v === '' || (Number(v) >= 1 && Number(v) <= 31), 'Informe entre 1 e 31'),
  bank_account_id: z.string(),
  status: z.string().min(1),
})

export type CreditCardFormValues = z.infer<typeof creditCardSchema>

export const closeInvoiceSchema = z.object({
  reference_month: z.string().min(1, 'Informe o mês de referência'),
})

export type CloseInvoiceFormValues = z.infer<typeof closeInvoiceSchema>
