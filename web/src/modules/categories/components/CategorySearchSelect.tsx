import { useCallback } from 'react'
import {
  SearchSelect,
  SearchSelectField,
  type SearchSelectOption,
} from '@/shared/design-system'
import {
  loadAllCategories,
  loadParentCategoryOptions,
  loadRootCategories,
  loadSubcategories,
  resolveCategoryLabel,
} from '../utils/category-select'

interface BaseCategorySelectProps {
  label?: string
  hint?: string
  required?: boolean
  placeholder?: string
  emptyMessage?: string
  disabled?: boolean
  className?: string
}

export function CategoryFilterSelect({
  value,
  onChange,
  type,
  className = 'w-96 min-w-96',
}: {
  value: string
  onChange: (value: string) => void
  type?: 'income' | 'expense'
  className?: string
}) {
  const loadOptions = useCallback(
    (search: string) => loadAllCategories(search, type),
    [type],
  )

  return (
    <SearchSelect
      value={value}
      onChange={(next) => onChange(next)}
      loadOptions={loadOptions}
      resolveLabel={resolveCategoryLabel}
      placeholder="Todas as categorias"
      emptyMessage="Nenhuma categoria encontrada"
      wrapOptionLabels
      className={className}
    />
  )
}

export function CategorySearchSelectField({
  name,
  categoryType,
  onSelectOption,
  placeholder = 'Buscar categoria...',
  ...props
}: BaseCategorySelectProps & {
  name: string
  categoryType: 'income' | 'expense'
  onSelectOption?: (option: SearchSelectOption) => void
}) {
  const loadOptions = useCallback(
    (search: string) => loadRootCategories(search, categoryType),
    [categoryType],
  )

  return (
    <SearchSelectField
      name={name}
      loadOptions={loadOptions}
      resolveLabel={resolveCategoryLabel}
      placeholder={placeholder}
      onSelectOption={onSelectOption}
      {...props}
    />
  )
}

export function SubcategorySearchSelectField({
  name,
  categoryType,
  categoryId,
  onCategoryChange,
  onSelectOption,
  placeholder = 'Buscar subcategoria...',
  ...props
}: BaseCategorySelectProps & {
  name: string
  categoryType: 'income' | 'expense'
  categoryId: string
  onCategoryChange?: (categoryId: string) => void
  onSelectOption?: (option: SearchSelectOption) => void
}) {
  const loadOptions = useCallback(
    (search: string) => loadSubcategories(search, categoryId, categoryType),
    [categoryId, categoryType],
  )

  return (
    <SearchSelectField
      name={name}
      loadOptions={loadOptions}
      resolveLabel={resolveCategoryLabel}
      placeholder={placeholder}
      onSelectOption={(option) => {
        if (option.parent_id && option.parent_id !== categoryId) {
          onCategoryChange?.(option.parent_id)
        }

        onSelectOption?.(option)
      }}
      {...props}
    />
  )
}

export function CategoryParentSearchSelectField({
  name,
  onSelectOption,
  placeholder = 'Buscar categoria principal...',
  ...props
}: BaseCategorySelectProps & {
  name: string
  onSelectOption?: (option: SearchSelectOption) => void
}) {
  const loadOptions = useCallback(
    (search: string) => loadParentCategoryOptions(search),
    [],
  )

  return (
    <SearchSelectField
      name={name}
      loadOptions={loadOptions}
      resolveLabel={resolveCategoryLabel}
      placeholder={placeholder}
      onSelectOption={onSelectOption}
      {...props}
    />
  )
}
