import { z } from 'zod'

export const costCenterSchema = z.object({
  name: z.string().min(1, 'Informe o nome'),
  status: z.string().min(1),
})

export type CostCenterFormValues = z.infer<typeof costCenterSchema>
