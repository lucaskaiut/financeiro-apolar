import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import {
  Button,
  ButtonLink,
  Card,
  CardContent,
  Form,
  Section,
  SelectField,
  TextField,
} from '@/shared/design-system'
import { isApiError } from '@/shared/api/errors'
import { applyApiErrorsToForm } from '@/shared/utils/forms'
import { costCenterSchema, type CostCenterFormValues } from '../schemas/cost-center.schema'
import type { CostCenterPayload } from '../services/cost-centers.service'

const STATUS_OPTIONS = [
  { value: 'active', label: 'Ativo' },
  { value: 'inactive', label: 'Inativo' },
]

interface CostCenterFormProps {
  mode: 'create' | 'edit'
  defaultValues?: Partial<CostCenterFormValues>
  submitting: boolean
  onSubmit: (payload: CostCenterPayload) => Promise<unknown>
}

export function CostCenterForm({ mode, defaultValues, submitting, onSubmit }: CostCenterFormProps) {
  const form = useForm<CostCenterFormValues>({
    resolver: zodResolver(costCenterSchema),
    defaultValues: {
      name: '',
      status: 'active',
      ...defaultValues,
    },
  })

  const handleSubmit = async (values: CostCenterFormValues) => {
    try {
      await onSubmit({
        name: values.name,
        status: values.status,
      })
    } catch (error) {
      if (isApiError(error) && error.status === 422) {
        applyApiErrorsToForm(form, error)
      }
    }
  }

  return (
    <Card>
      <CardContent>
        <Form form={form} onSubmit={handleSubmit} className="space-y-8">
          <Section title="Dados do centro de custo">
            <div className="grid gap-4 sm:grid-cols-2">
              <TextField name="name" label="Nome" placeholder="Ex.: Administrativo" required className="sm:col-span-2" />
              <SelectField name="status" label="Status" options={STATUS_OPTIONS} required />
            </div>
          </Section>

          <div className="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
            <ButtonLink to="/cost-centers" variant="secondary">
              Cancelar
            </ButtonLink>
            <Button type="submit" loading={submitting}>
              {mode === 'create' ? 'Criar centro de custo' : 'Salvar alterações'}
            </Button>
          </div>
        </Form>
      </CardContent>
    </Card>
  )
}
