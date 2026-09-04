import { useState } from 'react'
import { CalendarClock } from 'lucide-react'
import {
  Badge,
  Card,
  CardContent,
  DataTable,
  DateRangeFilter,
  EmptyState,
  FilterBar,
  Page,
  PageContent,
  PageHeader,
  Select,
  Skeleton,
  type Column,
} from '@/shared/design-system'
import { formatCurrency, formatDate, toLocalIsoDate } from '@/shared/utils/format'
import { addDays, toIsoDate } from '@/shared/utils/date'
import { useCostCenterOptions } from '@/modules/cost-centers/hooks/useCostCenters'
import { useProjectedCashFlow } from '../hooks/useCashFlow'
import type { ProjectedItem } from '../services/cash-flow.service'

const today = toLocalIsoDate()
const defaultTo = toIsoDate(addDays(new Date(), 30))

export default function CashFlowProjectedPage() {
  const [from, setFrom] = useState(today)
  const [to, setTo] = useState(defaultTo)
  const [costCenterId, setCostCenterId] = useState('')

  const costCenters = useCostCenterOptions()

  const query = useProjectedCashFlow({
    from,
    to,
    ...(costCenterId ? { cost_center_id: costCenterId } : {}),
  })

  const columns: Array<Column<ProjectedItem>> = [
    {
      key: 'due_date',
      header: 'Vencimento',
      render: (i) => <span className="text-muted">{formatDate(i.due_date)}</span>,
    },
    {
      key: 'description',
      header: 'Lançamento',
      render: (i) => (
        <div className="min-w-0">
          <p className="truncate font-medium text-foreground">{i.description}</p>
          <p className="truncate text-[13px] text-muted">{i.cost_center ?? '—'}</p>
        </div>
      ),
    },
    {
      key: 'installment',
      header: 'Parcela',
      render: (i) => (i.installment ? <Badge variant="neutral">{i.installment}</Badge> : <span className="text-muted">—</span>),
    },
    {
      key: 'value',
      header: 'Valor',
      render: (i) => (
        <span className={i.direction === 'in' ? 'font-medium text-success' : 'font-medium text-foreground'}>
          {formatCurrency(i.remaining_amount)}
        </span>
      ),
    },
  ]

  return (
    <Page>
      <PageHeader
        title="Fluxo de caixa projetado"
        description="Previsão de entradas e saídas futuras."
        breadcrumb={[{ label: 'Dashboard', to: '/dashboard' }, { label: 'Fluxo projetado' }]}
      />

      <PageContent>
        <FilterBar>
          <div className="flex flex-wrap gap-4">
            <DateRangeFilter
              from={from}
              to={to}
              onChange={({ from: nextFrom, to: nextTo }) => {
                setFrom(nextFrom)
                setTo(nextTo)
              }}
            />
            <Select
              aria-label="Centro de custo"
              className="w-52"
              value={costCenterId}
              onChange={(e) => setCostCenterId(e.target.value)}
              options={[{ value: '', label: 'Todos os centros' }, ...(costCenters.data ?? [])]}
            />
          </div>
        </FilterBar>

        {query.isPending ? (
          <Skeleton className="h-40" />
        ) : (
          <Card>
            <CardContent>
              <div className="flex flex-wrap items-center justify-between gap-3">
                <div>
                  <p className="text-[13px] text-muted">Saldo atual</p>
                  <p className="text-xl font-semibold text-foreground">{formatCurrency(query.data?.opening_balance)}</p>
                </div>
                <div>
                  <p className="text-[13px] text-success">Entradas previstas</p>
                  <p className="text-lg font-semibold text-success">{formatCurrency(query.data?.total_in)}</p>
                </div>
                <div>
                  <p className="text-[13px] text-danger">Saídas previstas</p>
                  <p className="text-lg font-semibold text-danger">{formatCurrency(query.data?.total_out)}</p>
                </div>
                <div>
                  <p className="text-[13px] text-muted">Saldo final projetado</p>
                  <p className="text-lg font-semibold text-foreground">
                    {formatCurrency((query.data?.series[query.data.series.length - 1]?.projected_balance ?? 0))}
                  </p>
                </div>
              </div>
            </CardContent>
          </Card>
        )}

        <Card>
          <CardContent>
            <h2 className="mb-3 text-sm font-semibold text-foreground">Projeção diária</h2>
            {query.isPending ? (
              <Skeleton className="h-64" />
            ) : (
              <div className="max-h-80 overflow-y-auto">
                <table className="min-w-full text-sm">
                  <thead className="sticky top-0 bg-surface">
                    <tr className="text-left text-xs tracking-wide text-muted uppercase">
                      <th className="px-3 py-2 font-medium">Data</th>
                      <th className="px-3 py-2 text-right font-medium">Entradas</th>
                      <th className="px-3 py-2 text-right font-medium">Saídas</th>
                      <th className="px-3 py-2 text-right font-medium">Saldo projetado</th>
                    </tr>
                  </thead>
                  <tbody>
                    {(query.data?.series ?? []).map((row) => (
                      <tr key={row.date} className="shadow-[inset_0_1px_0_var(--app-surface-2)]">
                        <td className="px-3 py-2 text-muted">{formatDate(row.date)}</td>
                        <td className="px-3 py-2 text-right text-success">{row.in ? formatCurrency(row.in) : '—'}</td>
                        <td className="px-3 py-2 text-right text-danger">{row.out ? formatCurrency(row.out) : '—'}</td>
                        <td className="px-3 py-2 text-right font-medium text-foreground">{formatCurrency(row.projected_balance)}</td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            )}
          </CardContent>
        </Card>

        <DataTable
          caption="Contas futuras"
          columns={columns}
          rows={query.data?.accounts ?? []}
          rowKey={(i) => i.id}
          loading={query.isPending}
          emptyState={<EmptyState icon={CalendarClock} title="Sem contas futuras no horizonte" />}
        />

        {query.data && query.data.installments.length > 0 && (
          <Card>
            <CardContent>
              <h2 className="mb-3 text-sm font-semibold text-foreground">Parcelas futuras</h2>
              <DataTable columns={columns} rows={query.data.installments} rowKey={(i) => i.id} />
            </CardContent>
          </Card>
        )}

        {query.data && query.data.recurrences.length > 0 && (
          <Card>
            <CardContent>
              <h2 className="mb-3 text-sm font-semibold text-foreground">Recorrências futuras</h2>
              <DataTable columns={columns} rows={query.data.recurrences} rowKey={(i) => i.id} />
            </CardContent>
          </Card>
        )}
      </PageContent>
    </Page>
  )
}
