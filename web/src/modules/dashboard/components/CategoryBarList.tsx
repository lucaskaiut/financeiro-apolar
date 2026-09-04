import { formatCurrency } from '@/shared/utils/format'
import type { CategoryTotal } from '../services/dashboard.service'
import { useMounted } from '../hooks/useMounted'

export function CategoryBarList({
  title,
  rows,
  accent = 'bg-danger',
  maxItems = 8,
  expanded = false,
}: {
  title: string
  rows: CategoryTotal[]
  accent?: string
  maxItems?: number
  expanded?: boolean
}) {
  const mounted = useMounted()
  const visibleRows = rows.slice(0, maxItems)
  const max = Math.max(...visibleRows.map((r) => r.total), 1)

  if (rows.length === 0) {
    return (
      <div>
        <h3 className="mb-3 text-sm font-semibold text-foreground">{title}</h3>
        <p className="text-[13px] text-muted">Nenhuma movimentação no mês.</p>
      </div>
    )
  }

  return (
    <div>
      <div className="mb-4 flex items-center justify-between gap-3">
        <h3 className="text-sm font-semibold text-foreground">{title}</h3>
        <span className="text-[12px] text-muted">
          {visibleRows.length} de {rows.length} {rows.length === 1 ? 'categoria' : 'categorias'}
        </span>
      </div>
      <div className={expanded ? 'grid gap-4 sm:grid-cols-2' : 'space-y-3'}>
        {visibleRows.map((row, i) => (
          <div key={row.category}>
            <div className="mb-1.5 flex items-center justify-between gap-3 text-[13px]">
              <span className="min-w-0 truncate font-medium text-foreground" title={row.category}>
                {row.category}
              </span>
              <span className="shrink-0 tabular-nums text-muted">{formatCurrency(row.total)}</span>
            </div>
            <div className={expanded ? 'h-3 w-full overflow-hidden rounded-full bg-surface-2' : 'h-2 w-full overflow-hidden rounded-full bg-surface-2'}>
              <div
                className={`h-full rounded-full ${accent}`}
                style={{
                  width: mounted ? `${(row.total / max) * 100}%` : '0%',
                  transition: `width 600ms ease-out ${i * 40}ms`,
                }}
              />
            </div>
          </div>
        ))}
      </div>
    </div>
  )
}
