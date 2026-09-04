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
import { companySchema, type CompanyFormValues } from '../schemas/company.schema'
import type { CompanyPayload } from '../services/companies.service'

const STATUS_OPTIONS = [
  { value: 'active', label: 'Ativo' },
  { value: 'inactive', label: 'Inativo' },
]

interface CompanyFormProps {
  mode: 'create' | 'edit'
  defaultValues?: Partial<CompanyFormValues>
  submitting: boolean
  onSubmit: (payload: CompanyPayload) => Promise<unknown>
}

export function CompanyForm({ mode, defaultValues, submitting, onSubmit }: CompanyFormProps) {
  const form = useForm<CompanyFormValues>({
    resolver: zodResolver(companySchema),
    defaultValues: {
      name: '',
      status: 'active',
      ...defaultValues,
    },
  })

  const handleSubmit = async (values: CompanyFormValues) => {
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
          <Section title="Dados da empresa">
            <div className="grid gap-4 sm:grid-cols-2">
              <TextField name="name" label="Nome" placeholder="Ex.: Imobiliária Apolar" required className="sm:col-span-2" />
              <SelectField name="status" label="Status" options={STATUS_OPTIONS} required />
            </div>
          </Section>

          <div className="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
            <ButtonLink to="/companies" variant="secondary">
              Cancelar
            </ButtonLink>
            <Button type="submit" loading={submitting}>
              {mode === 'create' ? 'Criar empresa' : 'Salvar alterações'}
            </Button>
          </div>
        </Form>
      </CardContent>
    </Card>
  )
}
