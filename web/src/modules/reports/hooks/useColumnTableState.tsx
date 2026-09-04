import { useCallback, useState } from 'react'
import { ColumnHeaderFilter } from '../components/ColumnHeaderFilter'
import {
  clearColumnState,
  setColumnRangeFilter,
  setColumnSort,
  setColumnTextFilter,
  type ColumnSortDirection,
  type ColumnTableSort,
  type ColumnTableState,
} from '../utils/column-table'

export type ColumnHeaderOptions = {
  align?: 'start' | 'end'
  variant?: 'text' | 'range'
  placeholder?: string
}

export function useColumnTableState(initialSort: ColumnTableSort | null = null) {
  const [columnState, setColumnState] = useState<ColumnTableState>({
    filters: {},
    sort: initialSort,
  })

  const renderColumnHeader = useCallback(
    (key: string, label: string, options?: ColumnHeaderOptions) => {
      const variant = options?.variant ?? 'text'
      const shared = {
        label,
        filter: columnState.filters[key],
        sortDirection: (columnState.sort?.key === key ? columnState.sort.direction : null) as ColumnSortDirection | null,
        onSort: (direction: ColumnSortDirection) => setColumnState((current) => setColumnSort(current, key, direction)),
        onClear: () => setColumnState((current) => clearColumnState(current, key)),
        align: options?.align,
      }

      if (variant === 'range') {
        return (
          <ColumnHeaderFilter
            {...shared}
            variant="range"
            onRangeChange={(range) => setColumnState((current) => setColumnRangeFilter(current, key, range))}
          />
        )
      }

      return (
        <ColumnHeaderFilter
          {...shared}
          variant="text"
          onFilterChange={(value) => setColumnState((current) => setColumnTextFilter(current, key, value))}
          filterPlaceholder={options?.placeholder}
        />
      )
    },
    [columnState],
  )

  return { columnState, setColumnState, renderColumnHeader }
}
