import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { z } from 'zod'
import { Button, Form, Modal, TextField } from '@/shared/design-system'
import { formatCurrency, toLocalIsoDate } from '@/shared/utils/format'
import type { Account } from '@/shared/types/models'
import { isApiError } from '@/shared/api/errors'
import { applyApiErrorsToForm } from '@/shared/utils/forms'

const settleSchema = z.object({
  value: z.string().refine((v) => v !== '' && Number(v) > 0, 'Informe um valor válido'),
  settled_at: z.string(),
  method: z.string(),
})

type SettleFormValues = z.infer<typeof settleSchema>

export function SettleDialog({
  account,
  open,
  submitting,
  onClose,
  onConfirm,
}: {
  account: Account | null
  open: boolean
  submitting: boolean
  onClose: () => void
  onConfirm: (payload: { value: number; settled_at: string; method: string | null }) => void
}) {
  const form = useForm<SettleFormValues>({
    resolver: zodResolver(settleSchema),
    defaultValues: {
      value: account ? String(account.remaining_amount) : '',
      settled_at: toLocalIsoDate(),
      method: '',
    },
    values: account
      ? {
          value: String(account.remaining_amount),
          settled_at: toLocalIsoDate(),
          method: '',
        }
      : undefined,
  })

  const handleSubmit = async (values: SettleFormValues) => {
    try {
      await onConfirm({ value: Number(values.value), settled_at: values.settled_at, method: values.method || null })
    } catch (error) {
      if (isApiError(error) && error.status === 422) {
        applyApiErrorsToForm(form, error)
      }
    }
  }

  return (
    <Modal open={open} onClose={onClose} title="Registrar baixa">
      {account && (
        <Form form={form} onSubmit={handleSubmit} className="space-y-4">
          <div className="rounded-lg bg-surface-2/60 p-3 text-sm">
            <p className="font-medium text-foreground">{account.description}</p>
            <p className="mt-1 text-muted">
              Valor total: {formatCurrency(account.value)} · Restante: {formatCurrency(account.remaining_amount)}
            </p>
          </div>

          <div className="grid gap-4 sm:grid-cols-2">
            <TextField name="value" label="Valor da baixa" type="number" step="0.01" min="0" required />
            <TextField name="settled_at" label="Data da baixa" type="date" required />
            <TextField name="method" label="Forma de pagamento" placeholder="Ex.: Pix, boleto..." className="sm:col-span-2" />
          </div>

          <div className="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
            <Button variant="secondary" onClick={onClose}>
              Cancelar
            </Button>
            <Button type="submit" loading={submitting}>
              Confirmar baixa
            </Button>
          </div>
        </Form>
      )}
    </Modal>
  )
}
