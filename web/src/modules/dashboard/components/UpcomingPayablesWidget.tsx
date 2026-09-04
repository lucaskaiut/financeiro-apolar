import { formatCurrency, formatDate } from '@/shared/utils/format'
import { addDays, toIsoDate } from '@/shared/utils/date'
import type { DashboardAccount } from '../services/dashboard.service'
import { useMounted } from '../hooks/useMounted'

type DayPoint = {
  date: string
  label: string
  total: number
  count: number
}

function buildSeries(accounts: DashboardAccount[]): DayPoint[] {
  const today = new Date()
  const byDate = new Map<string, { total: number; count: number }>()

  for (const account of accounts) {
    const key = account.due_date.slice(0, 10)
    const current = byDate.get(key) ?? { total: 0, count: 0 }
    current.total += account.remaining_amount
    current.count += 1
    byDate.set(key, current)
  }

  return Array.from({ length: 8 }, (_, index) => {
    const date = toIsoDate(addDays(today, index))
    const point = byDate.get(date) ?? { total: 0, count: 0 }

    return {
      date,
      label: formatDate(date).slice(0, 5),
      total: point.total,
      count: point.count,
    }
  })
}

export function UpcomingPayablesWidget({
  accounts,
  total,
}: {
  accounts: DashboardAccount[]
  total: number
}) {
  const mounted = useMounted()
  const series = buildSeries(accounts)
  const values = series.map((point) => point.total)
  const max = Math.max(...values, 1)

  const width = 100
  const height = 40
  const paddingX = 4
  const paddingTop = 4
  const paddingBottom = 8

  const points = series.map((point, index) => {
    const x = paddingX + (index / Math.max(series.length - 1, 1)) * (width - paddingX * 2)
    const y = paddingTop + (1 - point.total / max) * (height - paddingTop - paddingBottom)

    return [x, y] as const
  })

  const line = points.map(([x, y]) => `${x.toFixed(2)},${y.toFixed(2)}`).join(' ')
  const area = `${paddingX},${height - paddingBottom} ${line} ${width - paddingX},${height - paddingBottom}`

  return (
    <div>
      <div className="mb-3 flex items-center justify-between gap-3">
        <h2 className="text-sm font-semibold text-foreground">Projeção de contas a pagar — próximos 7 dias</h2>
        <span className="text-[13px] font-medium text-danger">Total previsto: {formatCurrency(total)}</span>
      </div>

      {accounts.length === 0 ? (
        <p className="text-[13px] text-muted">Nenhuma conta a pagar nos próximos 7 dias.</p>
      ) : (
        <div>
          <svg viewBox={`0 0 ${width} ${height}`} preserveAspectRatio="none" className="h-44 w-full">
            <defs>
              <linearGradient id="payables-7d-fill" x1="0" y1="0" x2="0" y2="1">
                <stop offset="0%" stopColor="var(--app-danger)" stopOpacity="0.28" />
                <stop offset="100%" stopColor="var(--app-danger)" stopOpacity="0" />
              </linearGradient>
            </defs>

            <line
              x1={paddingX}
              y1={height - paddingBottom}
              x2={width - paddingX}
              y2={height - paddingBottom}
              className="stroke-[color:var(--app-surface-3)]"
              strokeWidth="0.4"
              strokeDasharray="2 2"
              vectorEffect="non-scaling-stroke"
            />

            <polygon
              points={area}
              fill="url(#payables-7d-fill)"
              style={{ opacity: mounted ? 1 : 0, transition: 'opacity 600ms ease-out 200ms' }}
            />
            <polyline
              points={line}
              fill="none"
              stroke="var(--app-danger)"
              strokeWidth="0.7"
              strokeLinejoin="round"
              strokeLinecap="round"
              strokeDasharray="500"
              strokeDashoffset={mounted ? 0 : 500}
              style={{ transition: 'stroke-dashoffset 900ms ease-out' }}
              vectorEffect="non-scaling-stroke"
            />

            {points.map(([x, y], index) => (
              <circle
                key={series[index].date}
                cx={x}
                cy={y}
                r="0.9"
                fill="var(--app-danger)"
                style={{ opacity: mounted ? 1 : 0, transition: `opacity 300ms ease-out ${850 + index * 40}ms` }}
              >
                <title>
                  {`${formatDate(series[index].date)}: ${formatCurrency(series[index].total)} (${series[index].count} conta${series[index].count === 1 ? '' : 's'})`}
                </title>
              </circle>
            ))}
          </svg>

          <div
            className="mt-1 grid gap-0"
            style={{ gridTemplateColumns: `repeat(${series.length}, minmax(0, 1fr))` }}
          >
            {series.map((point) => (
              <span key={point.date} className="text-center text-[10px] font-medium tabular-nums text-muted">
                {point.label}
              </span>
            ))}
          </div>
        </div>
      )}
    </div>
  )
}
