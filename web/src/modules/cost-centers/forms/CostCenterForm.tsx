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

const TYPE_OPTIONS = [
  { value: 'checking', label: 'Conta corrente' },
  { value: 'savings', label: 'Conta poupança' },
  { value: 'investment', label: 'Investimento' },
  { value: 'other', label: 'Outro' },
]

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
      bank: '',
      agency: '',
      account: '',
      type: '',
      initial_balance: '',
      status: 'active',
      ...defaultValues,
    },
  })

  const handleSubmit = async (values: CostCenterFormValues) => {
    try {
      await onSubmit({
        name: values.name,
        bank: values.bank || null,
        agency: values.agency || null,
        account: values.account || null,
        type: values.type,
        initial_balance: values.initial_balance === '' ? 0 : Number(values.initial_balance),
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
          <Section title="Dados da conta bancária">
            <div className="grid gap-4 sm:grid-cols-2">
              <TextField name="name" label="Nome" placeholder="Ex.: Banco A - Conta principal" required className="sm:col-span-2" />
              <TextField name="bank" label="Banco" placeholder="Ex.: Banco do Brasil" />
              <TextField name="agency" label="Agência" />
              <TextField name="account" label="Conta" />
              <SelectField name="type" label="Tipo" options={TYPE_OPTIONS} placeholder="Selecione" required />
            </div>
          </Section>

          <Section title="Saldo e status">
            <div className="grid gap-4 sm:grid-cols-2">
              <TextField name="initial_balance" label="Saldo inicial" type="number" step="0.01" min="0" />
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
