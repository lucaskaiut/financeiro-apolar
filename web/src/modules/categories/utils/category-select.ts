import type { SearchSelectOption } from '@/shared/design-system'
import { categoriesService } from '../services/categories.service'

type CategoryOptionSource = {
  id: string
  name: string
  parent_id?: string | null
  parent_name?: string | null
  type?: string
}

export function formatCategoryLabel(category: Pick<CategoryOptionSource, 'name' | 'parent_id' | 'parent_name'>): string {
  if (category.parent_id && category.parent_name) {
    return `${category.parent_name} > ${category.name}`
  }

  return category.name
}

function mapCategoryOption(
  category: CategoryOptionSource,
  options?: { includeParent?: boolean },
): SearchSelectOption {
  return {
    value: category.id,
    label: options?.includeParent ? formatCategoryLabel(category) : category.name,
    parent_id: category.parent_id,
    type: category.type,
  }
}

export async function resolveCategoryLabel(id: string): Promise<SearchSelectOption | null> {
  try {
    const category = await categoriesService.get(id)

    return mapCategoryOption(category, { includeParent: true })
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

  return result.data.map((category) => mapCategoryOption(category))
}

export async function loadParentCategoryOptions(search: string): Promise<SearchSelectOption[]> {
  const result = await categoriesService.list({
    search: search || undefined,
    parent: 'root',
    per_page: 20,
  })

  return result.data.map((category) => mapCategoryOption(category))
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

  return result.data.map((category) => mapCategoryOption(category))
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

  return result.data.map((category) => mapCategoryOption(category, { includeParent: true }))
}
