import { z } from 'zod'

export const categorySchema = z.object({
  name: z.string().min(1, 'Informe o nome'),
  type: z.enum(['income', 'expense']),
  color: z.string(),
  status: z.string().min(1),
  parent_id: z.string(),
})

export type CategoryFormValues = z.infer<typeof categorySchema>
