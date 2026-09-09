import { useState } from 'react'
import { ArrowDownLeft, ArrowUpRight, TrendingUp } from 'lucide-react'
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
  Skeleton,
  type Column,
} from '@/shared/design-system'
import { formatCurrency, formatDate, toLocalIsoDate } from '@/shared/utils/format'
import { CategoryFilterSelect } from '@/modules/categories/components/CategorySearchSelect'
import { CostCenterFilter } from '@/modules/reports/components/CostCenterFilter'
import { useRealizedCashFlow } from '../hooks/useCashFlow'
import type { RealizedEntry } from '../services/cash-flow.service'

const today = toLocalIsoDate()
const firstOfMonth = toLocalIsoDate(new Date(new Date().getFullYear(), new Date().getMonth(), 1))

export default function CashFlowRealizedPage() {
  const [from, setFrom] = useState(firstOfMonth)
  const [to, setTo] = useState(today)
  const [costCenterId, setCostCenterId] = useState('')
  const [categoryId, setCategoryId] = useState('')

  const query = useRealizedCashFlow({
    ...(from ? { from } : {}),
    ...(to ? { to } : {}),
    ...(costCenterId ? { cost_center_id: costCenterId } : {}),
    ...(categoryId ? { category_id: categoryId } : {}),
  })

  const columns: Array<Column<RealizedEntry>> = [
    {
      key: 'date',
      header: 'Data',
      render: (e) => <span className="text-muted">{formatDate(e.date)}</span>,
    },
    {
      key: 'description',
      header: 'Lançamento',
      render: (e) => (
        <div className="min-w-0">
          <p className="truncate font-medium text-foreground">{e.description}</p>
          <p className="truncate text-[13px] text-muted">
            {e.bank_account ?? '—'}
            {e.category ? ` · ${e.category}` : ''}
          </p>
        </div>
      ),
    },
    {
      key: 'value',
      header: 'Valor',
      render: (e) => (
        <div className="flex items-center justify-end gap-2">
          {e.is_transfer && <Badge variant="neutral">Transferência</Badge>}
          <span className={e.direction === 'in' ? 'font-medium text-success' : 'font-medium text-foreground'}>
            {e.direction === 'in' ? '+' : '-'} {formatCurrency(e.value)}
          </span>
        </div>
      ),
    },
  ]

  return (
    <Page>
      <PageHeader
        title="Fluxo de caixa realizado"
        description="Entradas e saídas efetivamente realizadas no período."
        breadcrumb={[{ label: 'Dashboard', to: '/dashboard' }, { label: 'Fluxo realizado' }]}
      />

      <PageContent>
        <FilterBar>
          <div className="flex flex-wrap gap-4">
            <DateRangeFilter
              from={from}
              to={to}
              showClear
              onChange={({ from: nextFrom, to: nextTo }) => {
                setFrom(nextFrom)
                setTo(nextTo)
              }}
            />
            <div className="flex flex-wrap items-center gap-2">
              <CostCenterFilter value={costCenterId} onChange={setCostCenterId} />
              <CategoryFilterSelect value={categoryId} onChange={setCategoryId} />
            </div>
          </div>
        </FilterBar>

        {query.isPending ? (
          <div className="grid gap-4 sm:grid-cols-3">
            <Skeleton className="h-24" />
            <Skeleton className="h-24" />
            <Skeleton className="h-24" />
          </div>
        ) : (
          <div className="grid gap-4 sm:grid-cols-3">
            <SummaryCard icon={TrendingUp} label="Saldo inicial" value={query.data?.opening_balance ?? 0} />
            <SummaryCard icon={ArrowUpRight} label="Entradas" value={query.data?.total_in ?? 0} accent="text-success" />
            <SummaryCard icon={ArrowDownLeft} label="Saídas" value={query.data?.total_out ?? 0} accent="text-danger" />
          </div>
        )}

        {query.data && (
          <Card>
            <CardContent className="flex items-center justify-between">
              <span className="text-sm text-muted">Saldo final do período</span>
              <span className={`text-xl font-semibold ${query.data.final_balance >= 0 ? 'text-success' : 'text-danger'}`}>
                {formatCurrency(query.data.final_balance)}
              </span>
            </CardContent>
          </Card>
        )}

        <DataTable
          caption="Movimentações realizadas"
          columns={columns}
          rows={query.data?.entries ?? []}
          rowKey={(e) => e.id}
          loading={query.isPending}
          emptyState={
            <EmptyState
              icon={TrendingUp}
              title={from || to ? 'Nenhuma movimentação no período' : 'Nenhuma movimentação encontrada'}
            />
          }
        />
      </PageContent>
    </Page>
  )
}

function SummaryCard({ icon: Icon, label, value, accent = '' }: { icon: typeof ArrowUpRight; label: string; value: number; accent?: string }) {
  return (
    <Card>
      <CardContent className="flex items-center gap-3">
        <span className="flex size-10 shrink-0 items-center justify-center rounded-xl bg-surface-2 text-muted">
          <Icon className="size-5" />
        </span>
        <div className="min-w-0">
          <p className="text-[13px] text-muted">{label}</p>
          <p className={`text-lg font-semibold ${accent || 'text-foreground'}`}>{formatCurrency(value)}</p>
        </div>
      </CardContent>
    </Card>
  )
}
