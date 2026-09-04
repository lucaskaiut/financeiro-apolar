import type { CategoryMatrix, CategoryMatrixCategoryGroup, CategoryMatrixCostCenterGroup } from '../services/reports.service'
import { applyColumnTableState, type ColumnTableState } from './column-table'

function recalculateGroupSubtotal(group: CategoryMatrixCostCenterGroup): CategoryMatrixCostCenterGroup {
  const keys = Object.keys(group.subtotal.amounts)
  const amounts: Record<string, number> = {}

  for (const key of keys) {
    amounts[key] = Math.round(
      group.categories.reduce((sum, category) => sum + (category.subtotal.amounts[key] ?? 0), 0) * 100,
    ) / 100
  }

  const total = Math.round(group.categories.reduce((sum, category) => sum + category.subtotal.total, 0) * 100) / 100

  return {
    ...group,
    subtotal: { amounts, total },
  }
}

/** Applies the same column filters/sort used on the category list to the pivot matrix. */
export function applyColumnFiltersToCategoryMatrix(
  matrix: CategoryMatrix,
  state: ColumnTableState,
): CategoryMatrix {
  const accessors = {
    category: (row: CategoryMatrixCategoryGroup) => row.category,
    total: (row: CategoryMatrixCategoryGroup) => row.subtotal.total,
  }

  const groups = matrix.groups
    .map((group) => {
      const categories = applyColumnTableState(group.categories, state, accessors)
      if (categories.length === 0) return null
      return recalculateGroupSubtotal({ ...group, categories })
    })
    .filter((group): group is CategoryMatrixCostCenterGroup => group !== null)

  const amountKeys = matrix.columns.map((column) => column.key)
  const grandAmounts: Record<string, number> = {}
  for (const key of amountKeys) {
    grandAmounts[key] = Math.round(groups.reduce((sum, group) => sum + (group.subtotal.amounts[key] ?? 0), 0) * 100) / 100
  }
  const grandTotal = Math.round(groups.reduce((sum, group) => sum + group.subtotal.total, 0) * 100) / 100

  return {
    ...matrix,
    groups,
    grand_total: {
      amounts: grandAmounts,
      total: grandTotal,
    },
  }
}
