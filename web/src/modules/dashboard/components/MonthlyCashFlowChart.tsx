import type { CashFlowMonth } from '../services/dashboard.service'
import { useMounted } from '../hooks/useMounted'

export function MonthlyCashFlowChart({ data }: { data: CashFlowMonth[] }) {
  const mounted = useMounted()
  const max = Math.max(...data.map((d) => Math.max(d.income, d.expense)), 1)

  return (
    <div>
      <div className="mb-2 flex items-center gap-4 text-[13px] text-muted">
        <span className="flex items-center gap-1.5">
          <span className="size-2.5 rounded-sm bg-success" /> Entradas
        </span>
        <span className="flex items-center gap-1.5">
          <span className="size-2.5 rounded-sm bg-danger" /> Saídas
        </span>
      </div>

      <div className="flex h-44 items-end gap-2">
        {data.map((month, i) => (
          <div key={month.month} className="flex h-full flex-1 flex-col items-center">
            <div className="flex w-full flex-1 items-end justify-center gap-1 pb-1">
              <div
                title={`${month.label} — Entradas: ${month.income.toLocaleString('pt-BR')}`}
                className="w-2 origin-bottom rounded-t-sm bg-success/80 transition-[height] duration-500 ease-out group-hover:bg-success"
                style={{
                  height: mounted ? `${Math.max((month.income / max) * 100, 1)}%` : '0%',
                  transitionDelay: `${i * 35}ms`,
                }}
              />
              <div
                title={`${month.label} — Saídas: ${month.expense.toLocaleString('pt-BR')}`}
                className="w-2 origin-bottom rounded-t-sm bg-danger/80 transition-[height] duration-500 ease-out group-hover:bg-danger"
                style={{
                  height: mounted ? `${Math.max((month.expense / max) * 100, 1)}%` : '0%',
                  transitionDelay: `${i * 35}ms`,
                }}
              />
            </div>
            <span className="text-[10px] font-medium text-muted">{month.label}</span>
          </div>
        ))}
      </div>
    </div>
  )
}
