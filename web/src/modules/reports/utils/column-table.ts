export type ColumnSortDirection = 'asc' | 'desc'

export interface ColumnTableSort {
  key: string
  direction: ColumnSortDirection
}

export type ColumnTextFilter = {
  kind: 'text'
  value: string
}

export type ColumnRangeFilter = {
  kind: 'range'
  from: string
  to: string
}

export type ColumnFilter = ColumnTextFilter | ColumnRangeFilter

export interface ColumnTableState {
  filters: Record<string, ColumnFilter>
  sort: ColumnTableSort | null
}

export type ColumnValueAccessor<T> = (row: T) => string | number | null | undefined

export const DEFAULT_COLUMN_TABLE_STATE: ColumnTableState = {
  filters: {},
  sort: null,
}

function normalizeFilterText(value: string): string {
  return value
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .toLowerCase()
    .trim()
}

export function parseFilterNumber(value: string): number | null {
  const trimmed = value.trim()
  if (!trimmed) return null

  let normalized = trimmed.replace(/[^\d,.-]/g, '')
  if (!normalized || normalized === '-' || normalized === '.' || normalized === ',') return null

  if (normalized.includes(',') && normalized.includes('.')) {
    normalized = normalized.replace(/\./g, '').replace(',', '.')
  } else if (normalized.includes(',')) {
    normalized = normalized.replace(',', '.')
  }

  const parsed = Number(normalized)
  return Number.isFinite(parsed) ? parsed : null
}

function isFilterActive(filter: ColumnFilter | undefined): boolean {
  if (!filter) return false
  if (filter.kind === 'text') return Boolean(filter.value.trim())
  return Boolean(filter.from.trim() || filter.to.trim())
}

function matchesTextFilter(value: string | number | null | undefined, query: string): boolean {
  const needle = normalizeFilterText(query)
  if (!needle) return true
  if (value == null) return false

  const haystack = String(value)
  if (normalizeFilterText(haystack).includes(needle)) return true

  const isoMatch = haystack.match(/^(\d{4})-(\d{2})-(\d{2})/)
  if (isoMatch) {
    const [, year, month, day] = isoMatch
    const brDate = `${day}/${month}/${year}`
    if (normalizeFilterText(brDate).includes(needle)) return true
  }

  return false
}

function matchesRangeFilter(value: string | number | null | undefined, from: string, to: string): boolean {
  if (value == null || typeof value !== 'number') return false

  const min = parseFilterNumber(from)
  const max = parseFilterNumber(to)

  if (min == null && max == null) return true
  if (min != null && value < min) return false
  if (max != null && value > max) return false
  return true
}

function compareValues(
  left: string | number | null | undefined,
  right: string | number | null | undefined,
  direction: ColumnSortDirection,
): number {
  const factor = direction === 'asc' ? 1 : -1

  if (typeof left === 'number' && typeof right === 'number') {
    if (left === right) return 0
    return left < right ? -1 * factor : 1 * factor
  }

  return String(left ?? '').localeCompare(String(right ?? ''), 'pt-BR', { sensitivity: 'base' }) * factor
}

export function applyColumnTableState<T>(
  rows: T[],
  state: ColumnTableState,
  accessors: Record<string, ColumnValueAccessor<T>>,
): T[] {
  let result = rows

  for (const [key, filter] of Object.entries(state.filters)) {
    if (!isFilterActive(filter)) continue
    const getValue = accessors[key]
    if (!getValue) continue

    result = result.filter((row) => {
      const value = getValue(row)
      if (filter.kind === 'range') {
        return matchesRangeFilter(value, filter.from, filter.to)
      }
      return matchesTextFilter(value, filter.value)
    })
  }

  if (!state.sort) return result

  const getValue = accessors[state.sort.key]
  if (!getValue) return result

  const { direction } = state.sort
  return [...result].sort((a, b) => compareValues(getValue(a), getValue(b), direction))
}

export function setColumnTextFilter(state: ColumnTableState, key: string, value: string): ColumnTableState {
  const filters = { ...state.filters }
  if (!value.trim()) {
    delete filters[key]
  } else {
    filters[key] = { kind: 'text', value }
  }
  return { ...state, filters }
}

export function setColumnRangeFilter(
  state: ColumnTableState,
  key: string,
  range: { from: string; to: string },
): ColumnTableState {
  const filters = { ...state.filters }
  if (!range.from.trim() && !range.to.trim()) {
    delete filters[key]
  } else {
    filters[key] = { kind: 'range', from: range.from, to: range.to }
  }
  return { ...state, filters }
}

/** @deprecated use setColumnTextFilter */
export function setColumnFilter(state: ColumnTableState, key: string, value: string): ColumnTableState {
  return setColumnTextFilter(state, key, value)
}

export function setColumnSort(
  state: ColumnTableState,
  key: string,
  direction: ColumnSortDirection,
): ColumnTableState {
  return { ...state, sort: { key, direction } }
}

export function clearColumnState(state: ColumnTableState, key: string): ColumnTableState {
  const filters = { ...state.filters }
  delete filters[key]
  return {
    filters,
    sort: state.sort?.key === key ? null : state.sort,
  }
}

export function isColumnFilterActive(filter: ColumnFilter | undefined): boolean {
  return isFilterActive(filter)
}
