export const NO_COST_CENTER = 'Sem centro de custo'

export interface CostCenterNamedGroup<T> {
  costCenter: string
  items: T[]
  total: number
}

export function getCostCenterName(value: string | null | undefined): string {
  return value?.trim() || NO_COST_CENTER
}

export function groupItemsByCostCenter<T>(
  items: T[],
  getCostCenter: (item: T) => string | null | undefined,
  getAmount: (item: T) => number,
): CostCenterNamedGroup<T>[] {
  const map = new Map<string, T[]>()

  for (const item of items) {
    const key = getCostCenterName(getCostCenter(item))
    const list = map.get(key) ?? []
    list.push(item)
    map.set(key, list)
  }

  return Array.from(map.entries())
    .map(([costCenter, groupItems]) => ({
      costCenter,
      items: groupItems,
      total: groupItems.reduce((sum, item) => sum + getAmount(item), 0),
    }))
    .sort((a, b) => a.costCenter.localeCompare(b.costCenter, 'pt-BR'))
}

export function sortCostCenterGroups<T extends { cost_center: string }>(groups: T[]): T[] {
  return [...groups].sort((a, b) => a.cost_center.localeCompare(b.cost_center, 'pt-BR'))
}
