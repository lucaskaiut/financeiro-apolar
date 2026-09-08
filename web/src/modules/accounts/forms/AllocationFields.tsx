import { useCallback, useEffect } from 'react'
import { useFieldArray, useFormContext, useWatch } from 'react-hook-form'
import { Plus, Trash2 } from 'lucide-react'
import {
  Button,
  SearchSelectField,
  SelectField,
  TextField,
  type SearchSelectOption,
} from '@/shared/design-system'
import { formatCurrency } from '@/shared/utils/format'
import { categoriesService } from '@/modules/categories/services/categories.service'
import { useCostCenterOptions } from '@/modules/cost-centers/hooks/useCostCenters'
import { emptyAllocationLine, type AccountFormValues } from '../schemas/account.schema'

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
  const formError = form.formState.errors.allocations?.message as string | undefined

  const loadCategories = useCallback(
    async (search: string): Promise<SearchSelectOption[]> => {
      const result = await categoriesService.list({
        search: search || undefined,
        type: categoryType,
        parent: 'root',
        per_page: 20,
      })

      return result.data.map((category) => ({ value: category.id, label: category.name }))
    },
    [categoryType],
  )

  const resolveLabel = useCallback(async (id: string): Promise<SearchSelectOption | null> => {
    try {
      const category = await categoriesService.get(id)
      return { value: category.id, label: category.name }
    } catch {
      return null
    }
  }, [])

  return (
    <div className="space-y-4">
      {fields.map((field, index) => (
        <AllocationRow
          key={field.id}
          index={index}
          categoryType={categoryType}
          costCenterOptions={costCenters.data ?? []}
          loadCategories={loadCategories}
          resolveLabel={resolveLabel}
          canRemove={fields.length > 2}
          onRemove={() => remove(index)}
        />
      ))}

      <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <Button type="button" variant="secondary" size="sm" onClick={() => append(emptyAllocationLine())}>
          <Plus className="size-4" />
          Adicionar linha
        </Button>

        <p
          className={`text-sm ${
            Math.abs(remaining) < 0.01 ? 'text-success' : remaining > 0 ? 'text-muted' : 'text-danger'
          }`}
        >
          Rateado {formatCurrency(allocated)} de {formatCurrency(value)}
          {Math.abs(remaining) >= 0.01 ? ` · ${remaining > 0 ? 'faltam' : 'excedeu'} ${formatCurrency(Math.abs(remaining))}` : ''}
        </p>
      </div>

      {formError && <p className="text-[13px] text-danger">{formError}</p>}
    </div>
  )
}

function AllocationRow({
  index,
  categoryType,
  costCenterOptions,
  loadCategories,
  resolveLabel,
  canRemove,
  onRemove,
}: {
  index: number
  categoryType: 'income' | 'expense'
  costCenterOptions: Array<{ value: string; label: string }>
  loadCategories: (search: string) => Promise<SearchSelectOption[]>
  resolveLabel: (id: string) => Promise<SearchSelectOption | null>
  canRemove: boolean
  onRemove: () => void
}) {
  const form = useFormContext<AccountFormValues>()
  const categoryId = useWatch({ control: form.control, name: `allocations.${index}.category_id` })

  const loadSubcategories = useCallback(
    async (search: string): Promise<SearchSelectOption[]> => {
      const result = await categoriesService.list({
        search: search || undefined,
        type: categoryType,
        parent: categoryId || 'sub',
        per_page: 20,
      })

      return result.data.map((category) => ({
        value: category.id,
        label: category.name,
        parent_id: category.parent_id,
      }))
    },
    [categoryId, categoryType],
  )

  return (
    <div className="rounded-xl border border-surface-3 p-4">
      <div className="grid gap-4 sm:grid-cols-2">
        <SearchSelectField
          name={`allocations.${index}.category_id`}
          label="Categoria"
          loadOptions={loadCategories}
          resolveLabel={resolveLabel}
          placeholder="Buscar categoria..."
          required
          onSelectOption={() => {
            form.setValue(`allocations.${index}.subcategory_id`, '')
          }}
        />
        <SearchSelectField
          name={`allocations.${index}.subcategory_id`}
          label="Subcategoria"
          loadOptions={loadSubcategories}
          resolveLabel={resolveLabel}
          placeholder="Buscar subcategoria..."
          onSelectOption={(option) => {
            if (option.parent_id && option.parent_id !== categoryId) {
              form.setValue(`allocations.${index}.category_id`, option.parent_id)
            }
          }}
        />
        <SelectField
          name={`allocations.${index}.cost_center_id`}
          label="Centro de custo"
          options={costCenterOptions}
          placeholder="Opcional"
        />
        <div className="flex items-end gap-2">
          <TextField
            name={`allocations.${index}.value`}
            label="Valor"
            type="number"
            step="0.01"
            min="0"
            required
            className="flex-1"
          />
          <Button
            type="button"
            variant="ghost"
            size="sm"
            className="mb-0.5 text-danger hover:bg-danger-soft hover:text-danger"
            disabled={!canRemove}
            onClick={onRemove}
            aria-label={`Remover linha ${index + 1}`}
          >
            <Trash2 className="size-4" />
          </Button>
        </div>
      </div>
    </div>
  )
}
