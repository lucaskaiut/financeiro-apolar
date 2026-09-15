import { SearchSelect, Select } from '@/shared/design-system'
import {
  loadRootCategories,
  loadSubcategories,
  resolveCategoryLabel,
} from '@/modules/categories/utils/category-select'
import { useCostCenterOptions } from '@/modules/cost-centers/hooks/useCostCenters'

export function CategorySelect({
  value,
  onChange,
  placeholder = 'Categoria',
}: {
  value: string
  onChange: (value: string) => void
  placeholder?: string
}) {
  return (
    <SearchSelect
      value={value}
      onChange={(next) => onChange(next)}
      loadOptions={(search) => loadRootCategories(search, 'expense')}
      resolveLabel={resolveCategoryLabel}
      placeholder={placeholder}
      emptyMessage="Nenhuma categoria"
    />
  )
}

export function SubcategorySelect({
  value,
  categoryId,
  onChange,
}: {
  value: string
  categoryId: string
  onChange: (value: string) => void
}) {
  return (
    <SearchSelect
      value={value}
      onChange={(next) => onChange(next)}
      loadOptions={(search) => (categoryId ? loadSubcategories(search, categoryId, 'expense') : Promise.resolve([]))}
      resolveLabel={resolveCategoryLabel}
      placeholder="Subcategoria"
      emptyMessage="Nenhuma subcategoria"
      disabled={!categoryId}
    />
  )
}

export function CostCenterSelect({
  value,
  onChange,
}: {
  value: string
  onChange: (value: string) => void
}) {
  const costCenters = useCostCenterOptions()

  return (
    <Select
      aria-label="Centro de custo"
      value={value}
      onChange={(event) => onChange(event.target.value)}
      options={costCenters.data ?? []}
      placeholder="Centro de custo"
    />
  )
}
