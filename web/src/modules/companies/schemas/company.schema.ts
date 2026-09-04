import { z } from 'zod'

export const companySchema = z.object({
  name: z.string().min(1, 'Informe o nome'),
  status: z.string().min(1),
})

export type CompanyFormValues = z.infer<typeof companySchema>
