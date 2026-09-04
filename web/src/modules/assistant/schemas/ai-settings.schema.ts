import { z } from 'zod'

export const aiSettingsSchema = z.object({
  enabled: z.boolean(),
  endpoint: z.string().trim().max(500),
  api_key: z.string().max(2000),
  model: z.string().trim().max(255),
  temperature: z
    .string()
    .refine(
      (value) => value.trim() === '' || (!Number.isNaN(Number(value)) && Number(value) >= 0 && Number(value) <= 2),
      'Informe um número entre 0 e 2',
    ),
  max_tokens: z
    .string()
    .refine(
      (value) => value.trim() === '' || (Number.isInteger(Number(value)) && Number(value) >= 1 && Number(value) <= 200000),
      'Informe um número inteiro entre 1 e 200000',
    ),
  system_prompt: z.string().max(5000),
})

export type AiSettingsFormValues = z.infer<typeof aiSettingsSchema>
