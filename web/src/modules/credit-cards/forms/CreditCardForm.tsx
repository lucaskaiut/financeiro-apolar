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
import { useBankAccountOptions } from '@/modules/bank-accounts/hooks/useBankAccounts'
import { creditCardSchema, type CreditCardFormValues } from '../schemas/credit-card.schema'
import type { CreditCardPayload } from '../services/credit-cards.service'

const STATUS_OPTIONS = [
  { value: 'active', label: 'Ativo' },
  { value: 'inactive', label: 'Inativo' },
]

interface CreditCardFormProps {
  mode: 'create' | 'edit'
  defaultValues?: Partial<CreditCardFormValues>
  submitting: boolean
  onSubmit: (payload: CreditCardPayload) => Promise<unknown>
}

export function CreditCardForm({ mode, defaultValues, submitting, onSubmit }: CreditCardFormProps) {
  const bankAccounts = useBankAccountOptions()

  const form = useForm<CreditCardFormValues>({
    resolver: zodResolver(creditCardSchema),
    defaultValues: {
      name: '',
      institution: '',
      limit: '',
      closing_day: '',
      due_day: '',
      bank_account_id: '',
      status: 'active',
      ...defaultValues,
    },
  })

  const handleSubmit = async (values: CreditCardFormValues) => {
    try {
      await onSubmit({
        name: values.name,
        institution: values.institution || null,
        limit: values.limit === '' ? null : Number(values.limit),
        closing_day: values.closing_day === '' ? null : Number(values.closing_day),
        due_day: values.due_day === '' ? null : Number(values.due_day),
        bank_account_id: values.bank_account_id || null,
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
          <Section title="Dados do cartão">
            <div className="grid gap-4 sm:grid-cols-2">
              <TextField name="name" label="Nome" placeholder="Ex.: Cartão corporativo" required className="sm:col-span-2" />
              <TextField name="institution" label="Instituição" placeholder="Ex.: Nubank" />
              <TextField name="limit" label="Limite" type="number" step="0.01" min="0" />
              <TextField name="closing_day" label="Dia de fechamento" type="number" min="1" max="31" />
              <TextField name="due_day" label="Dia de vencimento" type="number" min="1" max="31" />
              <SelectField
                name="bank_account_id"
                label="Conta para pagamento"
                options={bankAccounts.data ?? []}
                placeholder="Selecione"
              />
              <SelectField name="status" label="Status" options={STATUS_OPTIONS} required />
            </div>
          </Section>

          <div className="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
            <ButtonLink to="/credit-cards" variant="secondary">
              Cancelar
            </ButtonLink>
            <Button type="submit" loading={submitting}>
              {mode === 'create' ? 'Criar cartão' : 'Salvar alterações'}
            </Button>
          </div>
        </Form>
      </CardContent>
    </Card>
  )
}
