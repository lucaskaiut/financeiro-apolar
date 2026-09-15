import { useEffect } from 'react'
import { useFieldArray, useFormContext, useWatch } from 'react-hook-form'
import { TextField } from '@/shared/design-system'
import { formatCurrency } from '@/shared/utils/format'
import type { AccountFormValues } from '../schemas/account.schema'

function computeDueDate(start: string, interval: string, step: number): string {
  if (!start) return ''

  const date = new Date(`${start}T00:00:00`)
  if (Number.isNaN(date.getTime())) return ''

  if (interval === 'daily') date.setDate(date.getDate() + step)
  else if (interval === 'weekly') date.setDate(date.getDate() + step * 7)
  else date.setMonth(date.getMonth() + step)

  const pad = (n: number) => String(n).padStart(2, '0')

  return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`
}

function roundTo(value: number): number {
  return Math.round(value * 100) / 100
}

export function InstallmentItemsFields() {
  const form = useFormContext<AccountFormValues>()
  const { replace } = useFieldArray({ control: form.control, name: 'installment_items' })

  const quantity = Number(useWatch({ control: form.control, name: 'installment_quantity' }) || 0)
  const total = Number(useWatch({ control: form.control, name: 'value' }) || 0)
  const dueDate = useWatch({ control: form.control, name: 'due_date' })
  const interval = useWatch({ control: form.control, name: 'installment_interval' })
  const items = useWatch({ control: form.control, name: 'installment_items' }) ?? []

  const qty = Math.max(1, Math.min(120, Number.isFinite(quantity) ? quantity : 1))
  const equal = total > 0 ? roundTo(total / qty) : 0

  const sum = items.reduce((acc, line) => acc + Number(line.value || 0), 0)
  const remaining = roundTo(total - sum)

  useEffect(() => {
    const current = form.getValues('installment_items') ?? []

    const next = Array.from({ length: qty }, (_, index) => {
      const existing = current[index]

      return {
        value: existing && existing.value !== '' ? existing.value : equal > 0 ? String(equal) : '',
        due_date: existing?.due_date || computeDueDate(dueDate, interval, index),
      }
    })

    replace(next)
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [qty, dueDate, interval])

  const formError = form.formState.errors.installment_items?.message as string | undefined

  return (
    <div className="mt-4 space-y-3">
      {items.map((_item, index) => (
        <div key={index} className="grid gap-3 rounded-lg border border-surface-2 p-3 sm:grid-cols-[auto_1fr_1fr]">
          <div className="flex items-center text-sm font-medium text-foreground">
            {index + 1}/{qty}
          </div>
          <TextField
            name={`installment_items.${index}.value`}
            label="Valor"
            type="number"
            step="0.01"
            min="0"
          />
          <TextField
            name={`installment_items.${index}.due_date`}
            label="Vencimento"
            type="date"
          />
        </div>
      ))}

      <div className="flex items-center justify-between text-[13px] text-muted">
        <span>Total das parcelas: {formatCurrency(sum)}</span>
        <span className={Math.abs(remaining) < 0.01 ? 'text-success' : 'text-warning'}>
          {Math.abs(remaining) < 0.01 ? 'Valor batendo' : `Diferença: ${formatCurrency(remaining)}`}
        </span>
      </div>

      {formError && <p className="text-[13px] text-danger">{formError}</p>}
    </div>
  )
}
