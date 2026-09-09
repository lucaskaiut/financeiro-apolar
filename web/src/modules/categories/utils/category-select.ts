import type { SearchSelectOption } from '@/shared/design-system'
import { categoriesService } from '../services/categories.service'

function mapCategoryOption(category: { id: string; name: string; parent_id?: string | null; type?: string }): SearchSelectOption {
  return {
    value: category.id,
    label: category.name,
    parent_id: category.parent_id,
    type: category.type,
  }
}

export async function resolveCategoryLabel(id: string): Promise<SearchSelectOption | null> {
  try {
    const category = await categoriesService.get(id)

    return mapCategoryOption(category)
  } catch {
    return null
  }
}

export async function loadRootCategories(
  search: string,
  type: 'income' | 'expense',
): Promise<SearchSelectOption[]> {
  const result = await categoriesService.list({
    search: search || undefined,
    type,
    parent: 'root',
    per_page: 20,
  })

  return result.data.map(mapCategoryOption)
}

export async function loadParentCategoryOptions(search: string): Promise<SearchSelectOption[]> {
  const result = await categoriesService.list({
    search: search || undefined,
    parent: 'root',
    per_page: 20,
  })

  return result.data.map(mapCategoryOption)
}

export async function loadSubcategories(
  search: string,
  categoryId: string,
  type: 'income' | 'expense',
): Promise<SearchSelectOption[]> {
  const result = await categoriesService.list({
    search: search || undefined,
    type,
    parent: categoryId || 'sub',
    per_page: 20,
  })

  return result.data.map(mapCategoryOption)
}

export async function loadAllCategories(
  search: string,
  type?: 'income' | 'expense',
): Promise<SearchSelectOption[]> {
  const result = await categoriesService.list({
    search: search || undefined,
    ...(type ? { type } : {}),
    per_page: 20,
  })

  return result.data.map(mapCategoryOption)
}
