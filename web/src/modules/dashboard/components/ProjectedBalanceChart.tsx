import { formatCurrency } from '@/shared/utils/format'
import type { ProjectedDay } from '../services/dashboard.service'
import { useMounted } from '../hooks/useMounted'

export function ProjectedBalanceChart({ data }: { data: ProjectedDay[] }) {
  const mounted = useMounted()
  const values = data.map((d) => d.projected_balance)
  const min = Math.min(...values)
  const max = Math.max(...values)
  const range = max - min || 1

  const width = 100
  const height = 40
  const padding = 4

  const points = data.map((d, i) => {
    const x = padding + (i / Math.max(data.length - 1, 1)) * (width - padding * 2)
    const y = padding + (1 - (d.projected_balance - min) / range) * (height - padding * 2)

    return [x, y] as const
  })

  const line = points.map(([x, y]) => `${x.toFixed(2)},${y.toFixed(2)}`).join(' ')
  const area = `${padding},${height - padding} ${line} ${width - padding},${height - padding}`

  return (
    <div>
      <div className="mb-2 flex items-center justify-between text-[13px] text-muted">
        <span>Saldo projetado (7 dias)</span>
        <span className="font-medium text-foreground">
          {formatCurrency(values[values.length - 1] ?? 0)}
        </span>
      </div>

      <svg viewBox={`0 0 ${width} ${height}`} preserveAspectRatio="none" className="h-44 w-full">
        <defs>
          <linearGradient id="projected-fill" x1="0" y1="0" x2="0" y2="1">
            <stop offset="0%" stopColor="var(--app-primary)" stopOpacity="0.25" />
            <stop offset="100%" stopColor="var(--app-primary)" stopOpacity="0" />
          </linearGradient>
        </defs>

        <line
          x1={padding}
          y1={height - padding}
          x2={width - padding}
          y2={height - padding}
          className="stroke-[color:var(--app-surface-3)]"
          strokeWidth="0.4"
          strokeDasharray="2 2"
          vectorEffect="non-scaling-stroke"
        />

        <polygon
          points={area}
          fill="url(#projected-fill)"
          style={{ opacity: mounted ? 1 : 0, transition: 'opacity 600ms ease-out 300ms' }}
        />
        <polyline
          points={line}
          fill="none"
          stroke="var(--app-primary)"
          strokeWidth="0.6"
          strokeLinejoin="round"
          strokeLinecap="round"
          strokeDasharray="500"
          strokeDashoffset={mounted ? 0 : 500}
          style={{ transition: 'stroke-dashoffset 900ms ease-out' }}
          vectorEffect="non-scaling-stroke"
        />

        {points.map(([x, y], i) => (
          <circle
            key={i}
            cx={x}
            cy={y}
            r="0.7"
            fill="var(--app-primary)"
            style={{ opacity: mounted ? 1 : 0, transition: `opacity 300ms ease-out ${900 + i * 15}ms` }}
          >
            <title>{`${data[i].date}: ${formatCurrency(data[i].projected_balance)}`}</title>
          </circle>
        ))}
      </svg>
    </div>
  )
}
