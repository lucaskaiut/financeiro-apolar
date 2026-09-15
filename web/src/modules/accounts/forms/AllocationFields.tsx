import { useEffect } from 'react'
import { useFieldArray, useFormContext, useWatch } from 'react-hook-form'
import { CheckCircle2, Plus, Trash2, TriangleAlert } from 'lucide-react'
import {
  Button,
  SelectField,
  TextField,
} from '@/shared/design-system'
import {
  CategorySearchSelectField,
  SubcategorySearchSelectField,
} from '@/modules/categories/components/CategorySearchSelect'
import { formatCurrency } from '@/shared/utils/format'
import { useCostCenterOptions } from '@/modules/cost-centers/hooks/useCostCenters'
import { emptyAllocationLine, type AccountFormValues } from '../schemas/account.schema'

const GRID = 'sm:grid-cols-[minmax(0,2fr)_minmax(0,2fr)_minmax(0,1.5fr)_minmax(0,1fr)_2.5rem]'

export function AllocationFields({ categoryType }: { categoryType: 'income' | 'expense' }) {
  const form = useFormContext<AccountFormValues>()
  const { fields, append, remove, replace } = useFieldArray({ control: form.control, name: 'allocations' })
  const costCenters = useCostCenterOptions()

  useEffect(() => {
    if (fields.length >= 2) return

    replace([
      {
        category_id: form.getValues('category_id'),
        subcategory_id: form.getValues('subcategory_id'),
        cost_center_id: form.getValues('cost_center_id'),
        value: form.getValues('value'),
      },
      emptyAllocationLine(),
    ])
  }, [fields.length, form, replace])

  const value = Number(useWatch({ control: form.control, name: 'value' }) || 0)
  const allocations = useWatch({ control: form.control, name: 'allocations' }) ?? []
  const allocated = allocations.reduce((sum, line) => sum + Number(line.value || 0), 0)
  const remaining = Math.round((value - allocated) * 100) / 100
  const complete = Math.abs(remaining) < 0.01
  const formError = form.formState.errors.allocations?.message as string | undefined

  return (
    <div className="space-y-4">
      <div className={`hidden gap-3 px-1 text-xs font-medium tracking-wide text-muted uppercase sm:grid ${GRID}`}>
        <span>Categoria</span>
        <span>Subcategoria</span>
        <span>Centro de custo</span>
        <span className="text-right">Valor</span>
        <span className="sr-only">Ações</span>
      </div>

      {fields.map((field, index) => (
        <AllocationRow
          key={field.id}
          index={index}
          categoryType={categoryType}
          costCenterOptions={costCenters.data ?? []}
          canRemove={fields.length > 2}
          onRemove={() => remove(index)}
        />
      ))}

      <Button type="button" variant="secondary" size="sm" onClick={() => append(emptyAllocationLine())}>
        <Plus className="size-4" />
        Adicionar rateio
      </Button>

      {formError && <p className="text-[13px] text-danger">{formError}</p>}

      <div className="rounded-lg bg-surface-2/50 p-4 text-sm">
        <div className="space-y-1.5">
          <SummaryLine label="Valor total" value={formatCurrency(value)} />
          <SummaryLine label="Rateado" value={formatCurrency(allocated)} />
        </div>
        <div className="mt-2 flex items-center justify-between border-t border-surface-3 pt-2.5 font-medium text-foreground">
          <span>Restante</span>
          <span className="tabular-nums">{formatCurrency(Math.abs(remaining))}</span>
        </div>
        <div className={`mt-3 flex items-center gap-2 font-medium ${complete ? 'text-success' : 'text-warning'}`}>
          {complete ? <CheckCircle2 className="size-4 shrink-0" /> : <TriangleAlert className="size-4 shrink-0" />}
          <span>
            {complete
              ? 'Rateio concluído'
              : remaining > 0
                ? `Faltam ${formatCurrency(remaining)} para concluir o rateio`
                : `Excedeu ${formatCurrency(Math.abs(remaining))} no rateio`}
          </span>
        </div>
      </div>
    </div>
  )
}

function SummaryLine({ label, value }: { label: string; value: string }) {
  return (
    <div className="flex items-center justify-between text-muted">
      <span>{label}</span>
      <span className="tabular-nums">{value}</span>
    </div>
  )
}

function AllocationRow({
  index,
  categoryType,
  costCenterOptions,
  canRemove,
  onRemove,
}: {
  index: number
  categoryType: 'income' | 'expense'
  costCenterOptions: Array<{ value: string; label: string }>
  canRemove: boolean
  onRemove: () => void
}) {
  const form = useFormContext<AccountFormValues>()
  const categoryId = useWatch({ control: form.control, name: `allocations.${index}.category_id` }) ?? ''

  return (
    <div className={`grid grid-cols-1 gap-3 sm:items-start ${GRID}`}>
      <CategorySearchSelectField
        name={`allocations.${index}.category_id`}
        placeholder="Categoria"
        categoryType={categoryType}
        onSelectOption={() => {
          form.setValue(`allocations.${index}.subcategory_id`, '')
        }}
      />
      <SubcategorySearchSelectField
        name={`allocations.${index}.subcategory_id`}
        placeholder="Subcategoria"
        categoryType={categoryType}
        categoryId={categoryId}
        onCategoryChange={(parentId) => form.setValue(`allocations.${index}.category_id`, parentId)}
      />
      <SelectField
        name={`allocations.${index}.cost_center_id`}
        options={costCenterOptions}
        placeholder="Centro de custo"
      />
      <TextField
        name={`allocations.${index}.value`}
        placeholder="0,00"
        type="number"
        step="0.01"
        min="0"
      />
      <Button
        type="button"
        variant="ghost"
        size="sm"
        className="h-11 text-danger hover:bg-danger-soft hover:text-danger sm:w-10"
        disabled={!canRemove}
        onClick={onRemove}
        aria-label={`Remover linha ${index + 1}`}
      >
        <Trash2 className="size-4" />
      </Button>
    </div>
  )
}
