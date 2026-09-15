import { Input, Button } from '@/shared/design-system'
import { Plus, Trash2, Scale } from 'lucide-react'
import { formatCurrency } from '@/shared/utils/format'
import { CategorySelect, SubcategorySelect, CostCenterSelect } from './ClassificationSelects'
import { emptySplit, round, type SplitDraft } from '../types/import-draft'

export function SplitEditor({
  splits,
  total,
  onChange,
}: {
  splits: SplitDraft[]
  total: number
  onChange: (splits: SplitDraft[]) => void
}) {
  const allocated = round(splits.reduce((sum, part) => sum + (part.value || 0), 0))
  const remaining = round(total - allocated)
  const complete = Math.abs(remaining) < 0.01

  const updatePart = (id: string, patch: Partial<SplitDraft>) => {
    onChange(splits.map((part) => (part.id === id ? { ...part, ...patch } : part)))
  }

  const removePart = (id: string) => onChange(splits.filter((part) => part.id !== id))

  const addPart = () => onChange([...splits, emptySplit()])

  const divideEqually = () => {
    const count = splits.length || 1
    const equal = round(total / count)
    const values = Array.from({ length: count }, (_, index) =>
      index === count - 1 ? round(total - equal * (count - 1)) : equal,
    )

    onChange(splits.map((part, index) => ({ ...part, value: values[index] })))
  }

  return (
    <div className="space-y-3">
      <div className="hidden gap-3 px-1 text-xs font-medium tracking-wide text-muted uppercase sm:grid sm:grid-cols-[minmax(0,2fr)_minmax(0,1.5fr)_minmax(0,1.5fr)_minmax(0,1.2fr)_7rem_2.5rem]">
        <span>Descrição</span>
        <span>Categoria</span>
        <span>Subcategoria</span>
        <span>Centro de custo</span>
        <span className="text-right">Valor</span>
        <span className="sr-only">Ações</span>
      </div>

      {splits.map((part) => (
        <div
          key={part.id}
          className="grid grid-cols-1 gap-2 rounded-lg border border-surface-3 p-2 sm:grid-cols-[minmax(0,2fr)_minmax(0,1.5fr)_minmax(0,1.5fr)_minmax(0,1.2fr)_7rem_2.5rem] sm:items-center sm:border-0 sm:p-0"
        >
          <Input
            value={part.description}
            onChange={(event) => updatePart(part.id, { description: event.target.value })}
            placeholder="Descrição da parte"
            aria-label="Descrição da parte"
          />
          <CategorySelect
            value={part.category_id}
            onChange={(categoryId) => updatePart(part.id, { category_id: categoryId, subcategory_id: '' })}
          />
          <SubcategorySelect
            value={part.subcategory_id}
            categoryId={part.category_id}
            onChange={(subcategoryId) => updatePart(part.id, { subcategory_id: subcategoryId })}
          />
          <CostCenterSelect value={part.cost_center_id} onChange={(costCenterId) => updatePart(part.id, { cost_center_id: costCenterId })} />
          <Input
            value={part.value === 0 ? '' : String(part.value)}
            onChange={(event) => updatePart(part.id, { value: Number(event.target.value) || 0 })}
            type="number"
            step="0.01"
            min="0"
            placeholder="0,00"
            aria-label="Valor da parte"
            className="text-right"
          />
          <div className="flex sm:justify-end">
            <Button
              type="button"
              variant="ghost"
              size="sm"
              className="h-11 text-danger hover:bg-danger-soft hover:text-danger sm:w-10"
              disabled={splits.length <= 1}
              onClick={() => removePart(part.id)}
              aria-label="Remover parte"
            >
              <Trash2 className="size-4" />
            </Button>
          </div>
        </div>
      ))}

      <div className="flex flex-wrap items-center gap-2">
        <Button type="button" variant="secondary" size="sm" onClick={addPart}>
          <Plus className="size-4" />
          Adicionar parte
        </Button>
        <Button type="button" variant="ghost" size="sm" onClick={divideEqually}>
          <Scale className="size-4" />
          Dividir igualmente
        </Button>
        <span className={`ml-auto text-sm ${complete ? 'text-success' : 'text-warning'}`}>
          {complete
            ? 'Rateio concluído'
            : remaining > 0
              ? `Faltam ${formatCurrency(remaining)}`
              : `Excedeu ${formatCurrency(Math.abs(remaining))}`}
        </span>
      </div>
    </div>
  )
}
