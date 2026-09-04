import { Plus, Trash2 } from 'lucide-react'
import { useFieldArray, useFormContext } from 'react-hook-form'
import { Button, SelectField, TextField } from '@/shared/design-system'
import { useCompanyOptions } from '@/modules/companies/hooks/useCompanies'
import { useCostCenterOptions } from '@/modules/cost-centers/hooks/useCostCenters'
import type { AccountFormValues } from '../schemas/account.schema'

export function AllocationSection() {
  const form = useFormContext<AccountFormValues>()
  const useAllocations = form.watch('use_allocations')
  const { fields, append, remove } = useFieldArray({ control: form.control, name: 'allocations' })

  const costCenters = useCostCenterOptions()
  const companies = useCompanyOptions()

  if (!useAllocations) return null

  return (
    <div className="space-y-4">
      {fields.map((field, index) => (
        <div key={field.id} className="grid gap-4 rounded-lg border border-surface-3 p-4 sm:grid-cols-2">
          <SelectField
            name={`allocations.${index}.cost_center_id`}
            label="Centro de custo"
            options={costCenters.data ?? []}
            placeholder="Opcional"
          />
          <SelectField
            name={`allocations.${index}.company_id`}
            label="Empresa"
            options={companies.data ?? []}
            placeholder="Opcional"
          />
          <TextField
            name={`allocations.${index}.category_id`}
            label="ID da categoria"
            placeholder="UUID da categoria"
            required
          />
          <TextField
            name={`allocations.${index}.value`}
            label="Valor"
            type="number"
            step="0.01"
            min="0"
            placeholder="Valor fixo"
          />
          <TextField
            name={`allocations.${index}.percentage`}
            label="Percentual (%)"
            type="number"
            step="0.01"
            min="0"
            max="100"
            placeholder="Ou percentual"
          />
          <div className="flex items-end sm:col-span-2">
            <Button type="button" variant="ghost" size="sm" onClick={() => remove(index)} className="text-danger hover:bg-danger-soft hover:text-danger">
              <Trash2 className="size-4" />
              Remover linha
            </Button>
          </div>
        </div>
      ))}

      <Button
        type="button"
        variant="secondary"
        size="sm"
        onClick={() =>
          append({
            cost_center_id: '',
            company_id: '',
            category_id: '',
            value: '',
            percentage: '',
          })
        }
      >
        <Plus className="size-4" />
        Adicionar linha de rateio
      </Button>
    </div>
  )
}
