import { Fragment, useState, type ReactNode } from 'react'
import { ChevronRight } from 'lucide-react'
import { cn } from '@/shared/utils/cn'
import { Card } from './Card'
import { Skeleton } from './Skeleton'

export interface Column<T> {
  key: string
  header: ReactNode
  render: (row: T) => ReactNode
  className?: string
}

export interface DataTableProps<T> {
  columns: Array<Column<T>>
  rows: T[]
  rowKey: (row: T) => string | number
  loading?: boolean
  skeletonRows?: number
  emptyState?: ReactNode
  caption?: string
  onRowClick?: (row: T) => void
  /** Habilita linhas em accordeon; o conteúdo é exibido ao expandir a linha. */
  renderExpanded?: (row: T) => ReactNode
  /** Reduz espaçamentos e tipografia para caber mais colunas. */
  dense?: boolean
}

export function DataTable<T>({
  columns,
  rows,
  rowKey,
  loading = false,
  skeletonRows = 5,
  emptyState,
  caption,
  onRowClick,
  renderExpanded,
  dense = false,
}: DataTableProps<T>) {
  const showEmpty = !loading && rows.length === 0
  const expandable = renderExpanded !== undefined
  const headerCellClasses = dense ? 'px-3 py-2.5' : 'px-5 py-3'
  const bodyCellClasses = dense ? 'px-3 py-2.5' : 'px-5 py-3.5'
  const [expandedKeys, setExpandedKeys] = useState<Array<string | number>>([])

  const toggleExpanded = (key: string | number) => {
    setExpandedKeys((keys) => (keys.includes(key) ? keys.filter((item) => item !== key) : [...keys, key]))
  }

  const handleRowClick = (row: T) => {
    if (onRowClick) {
      onRowClick(row)
      return
    }

    if (expandable) toggleExpanded(rowKey(row))
  }

  const shouldIgnoreRowClick = (target: EventTarget | null) =>
    target instanceof Element && target.closest('button, a, input, select, textarea') !== null

  const colSpan = columns.length + (expandable ? 1 : 0)

  return (
    <Card className="overflow-hidden">
      <div className="overflow-x-auto">
        <table className={cn('min-w-full', dense ? 'text-[13px]' : 'text-sm')}>
          {caption && <caption className="sr-only">{caption}</caption>}
          <thead>
            <tr className="bg-surface-2/60 text-left text-xs tracking-wide text-muted uppercase">
              {expandable && (
                <th scope="col" className={cn('w-10', headerCellClasses)}>
                  <span className="sr-only">Expandir</span>
                </th>
              )}
              {columns.map((column) => (
                <th key={column.key} scope="col" className={cn(headerCellClasses, 'font-medium', column.className)}>
                  {column.header}
                </th>
              ))}
            </tr>
          </thead>
          <tbody>
            {loading
              ? Array.from({ length: skeletonRows }).map((_, index) => (
                  <tr key={index} className="shadow-[inset_0_1px_0_var(--app-surface-2)]">
                    {expandable && (
                      <td className={dense ? 'px-3 py-4' : 'px-5 py-4'}>
                        <Skeleton className="size-4" />
                      </td>
                    )}
                    {columns.map((column) => (
                      <td key={column.key} className={dense ? 'px-3 py-4' : 'px-5 py-4'}>
                        <Skeleton className="h-4 w-full max-w-40" />
                      </td>
                    ))}
                  </tr>
                ))
              : rows.map((row) => {
                  const key = rowKey(row)
                  const expanded = expandable && expandedKeys.includes(key)
                  const clickable = Boolean(onRowClick) || expandable

                  return (
                    <Fragment key={key}>
                      <tr
                        onClick={
                          clickable
                            ? (event) => {
                                if (!shouldIgnoreRowClick(event.target)) handleRowClick(row)
                              }
                            : undefined
                        }
                        onKeyDown={
                          onRowClick
                            ? (event) => {
                                if (event.key === 'Enter' || event.key === ' ') {
                                  event.preventDefault()
                                  onRowClick(row)
                                }
                              }
                            : undefined
                        }
                        tabIndex={onRowClick ? 0 : undefined}
                        role={onRowClick ? 'button' : undefined}
                        className={cn(
                          'shadow-[inset_0_1px_0_var(--app-surface-2)] transition-colors',
                          clickable && 'cursor-pointer',
                          onRowClick &&
                            'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary hover:bg-surface-2/40',
                          !onRowClick && expandable && 'hover:bg-surface-2/40',
                        )}
                      >
                        {expandable && (
                          <td className={cn(bodyCellClasses, 'align-middle')}>
                            <button
                              type="button"
                              onClick={() => toggleExpanded(key)}
                              aria-expanded={expanded}
                              aria-label={expanded ? 'Recolher detalhes' : 'Expandir detalhes'}
                              className="flex size-6 items-center justify-center rounded-md text-muted transition-colors hover:bg-surface-2 hover:text-foreground"
                            >
                              <ChevronRight
                                className={cn('size-4 transition-transform duration-200', expanded && 'rotate-90')}
                              />
                            </button>
                          </td>
                        )}
                        {columns.map((column) => (
                          <td key={column.key} className={cn(bodyCellClasses, 'align-middle', column.className)}>
                            {column.render(row)}
                          </td>
                        ))}
                      </tr>
                      {expanded && (
                        <tr className="shadow-[inset_0_1px_0_var(--app-surface-2)]">
                          <td colSpan={colSpan} className="p-0">
                            {renderExpanded(row)}
                          </td>
                        </tr>
                      )}
                    </Fragment>
                  )
                })}
          </tbody>
        </table>
      </div>
      {showEmpty && emptyState}
    </Card>
  )
}
