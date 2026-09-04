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
import { useCostCenterOptions } from '@/modules/cost-centers/hooks/useCostCenters'
import { toLocalIsoDate } from '@/shared/utils/format'
import { transferSchema, type TransferFormValues } from '../schemas/transfer.schema'
import type { TransferPayload } from '../services/transfers.service'

export function TransferForm({ submitting, onSubmit }: { submitting: boolean; onSubmit: (payload: TransferPayload) => Promise<unknown> }) {
  const costCenters = useCostCenterOptions()

  const form = useForm<TransferFormValues>({
    resolver: zodResolver(transferSchema),
    defaultValues: {
      from_cost_center_id: '',
      to_cost_center_id: '',
      value: '',
      date: toLocalIsoDate(),
      description: '',
    },
  })

  const handleSubmit = async (values: TransferFormValues) => {
    try {
      await onSubmit({
        from_cost_center_id: values.from_cost_center_id,
        to_cost_center_id: values.to_cost_center_id,
        value: Number(values.value),
        date: values.date,
        description: values.description || null,
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
          <Section title="Transferência entre contas" description="Gera automaticamente a saída e a entrada correspondentes.">
            <div className="grid gap-4 sm:grid-cols-2">
              <SelectField name="from_cost_center_id" label="Conta de origem" options={costCenters.data ?? []} placeholder="Selecione" required />
              <SelectField name="to_cost_center_id" label="Conta de destino" options={costCenters.data ?? []} placeholder="Selecione" required />
              <TextField name="value" label="Valor" type="number" step="0.01" min="0" required />
              <TextField name="date" label="Data" type="date" required />
              <TextField name="description" label="Descrição" className="sm:col-span-2" />
            </div>
          </Section>

          <div className="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
            <ButtonLink to="/transfers" variant="secondary">
              Cancelar
            </ButtonLink>
            <Button type="submit" loading={submitting}>
              Realizar transferência
            </Button>
          </div>
        </Form>
      </CardContent>
    </Card>
  )
}
