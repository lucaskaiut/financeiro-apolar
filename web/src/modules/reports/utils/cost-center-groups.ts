export const NO_COST_CENTER = 'Sem centro de custo'

export interface BankAccountNamedGroup<T> {
  costCenter: string
  items: T[]
  total: number
}

export function getBankAccountName(value: string | null | undefined): string {
  return value?.trim() || NO_COST_CENTER
}

export function groupItemsByBankAccount<T>(
  items: T[],
  getBankAccount: (item: T) => string | null | undefined,
  getAmount: (item: T) => number,
): BankAccountNamedGroup<T>[] {
  const map = new Map<string, T[]>()

  for (const item of items) {
    const key = getBankAccountName(getBankAccount(item))
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

export function sortBankAccountGroups<T extends { bank_account: string }>(groups: T[]): T[] {
  return [...groups].sort((a, b) => a.bank_account.localeCompare(b.bank_account, 'pt-BR'))
}
