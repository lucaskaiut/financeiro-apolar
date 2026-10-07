import type { PaginationMeta } from '@/shared/types/api'
import type { Category } from '@/shared/types/models'

export type CategoryListRow = Category & {
  depth: 0 | 1
  hasChildren: boolean
}

function compareByName(a: Category, b: Category): number {
  return a.name.localeCompare(b.name, 'pt-BR', { sensitivity: 'base' })
}

function normalizeSearch(search: string | undefined): string {
  return (search ?? '').trim().toLowerCase()
}

function nameMatches(category: Category, search: string): boolean {
  if (!search) return true
  return category.name.toLowerCase().includes(search)
}

export function groupCategoriesByParent(categories: Category[]): {
  roots: Category[]
  childrenByParentId: Map<string, Category[]>
} {
  const roots: Category[] = []
  const childrenByParentId = new Map<string, Category[]>()

  for (const category of categories) {
    if (!category.parent_id) {
      roots.push(category)
      continue
    }

    const siblings = childrenByParentId.get(category.parent_id) ?? []
    siblings.push(category)
    childrenByParentId.set(category.parent_id, siblings)
  }

  roots.sort(compareByName)
  for (const siblings of childrenByParentId.values()) {
    siblings.sort(compareByName)
  }

  return { roots, childrenByParentId }
}

export function filterRootCategoriesForSearch(
  roots: Category[],
  childrenByParentId: Map<string, Category[]>,
  search: string | undefined,
): Category[] {
  const term = normalizeSearch(search)
  if (!term) return roots

  return roots.filter((root) => {
    if (nameMatches(root, term)) return true
    const children = childrenByParentId.get(root.id) ?? []
    return children.some((child) => nameMatches(child, term))
  })
}

function visibleChildrenForRoot(
  root: Category,
  childrenByParentId: Map<string, Category[]>,
  search: string | undefined,
): Category[] {
  const children = childrenByParentId.get(root.id) ?? []
  const term = normalizeSearch(search)
  if (!term) return children
  if (nameMatches(root, term)) return children
  return children.filter((child) => nameMatches(child, term))
}

export function buildCategoryListRows(options: {
  categories: Category[]
  search?: string
  collapsedParentIds: ReadonlySet<string>
  page: number
  perPage: number
}): { rows: CategoryListRow[]; pagination: PaginationMeta } {
  const { categories, search, collapsedParentIds, page, perPage } = options
  const { roots, childrenByParentId } = groupCategoriesByParent(categories)
  const filteredRoots = filterRootCategoriesForSearch(roots, childrenByParentId, search)
  const total = filteredRoots.length
  const lastPage = Math.max(1, Math.ceil(total / perPage))
  const safePage = Math.min(Math.max(page, 1), lastPage)
  const start = (safePage - 1) * perPage
  const pageRoots = filteredRoots.slice(start, start + perPage)
  const term = normalizeSearch(search)
  const forceExpanded = term.length > 0

  const rows: CategoryListRow[] = []

  for (const root of pageRoots) {
    const children = childrenByParentId.get(root.id) ?? []
    const hasChildren = children.length > 0
    rows.push({ ...root, depth: 0, hasChildren })

    const expanded = forceExpanded || !collapsedParentIds.has(root.id)
    if (hasChildren && expanded) {
      for (const child of visibleChildrenForRoot(root, childrenByParentId, search)) {
        rows.push({ ...child, depth: 1, hasChildren: false })
      }
    }
  }

  const from = total === 0 ? null : start + 1
  const to = total === 0 ? null : start + pageRoots.length

  return {
    rows,
    pagination: {
      current_page: safePage,
      last_page: lastPage,
      per_page: perPage,
      total,
      from,
      to,
    },
  }
}
