import { useState } from 'react'
import { useNavigate, useSearchParams } from 'react-router'
import { CalendarRange, CreditCard } from 'lucide-react'
import {
  Badge,
  DataTable,
  EmptyState,
  FilterBar,
  Page,
  PageContent,
  PageHeader,
  Pagination,
  SearchInput,
  type Column,
} from '@/shared/design-system'
import { useDebounce } from '@/shared/hooks/useDebounce'
import { formatCurrency, formatDate } from '@/shared/utils/format'
import type { InstallmentGroup } from '@/shared/types/models'
import { useInstallmentsQuery } from '../hooks/useInstallments'

const PER_PAGE = 10

export default function InstallmentsListPage() {
  const [searchParams, setSearchParams] = useSearchParams()
  const [search, setSearch] = useState(searchParams.get('search') ?? '')
  const debouncedSearch = useDebounce(search)
  const page = Number(searchParams.get('page') ?? 1)

  const navigate = useNavigate()

  const query = useInstallmentsQuery({ page, per_page: PER_PAGE, search: debouncedSearch || undefined })

  const columns: Array<Column<InstallmentGroup>> = [
    {
      key: 'description',
      header: 'Parcelamento',
      render: (p) => (
        <div className="min-w-0">
          <div className="flex items-center gap-2">
            <p className="truncate font-medium text-foreground">{p.description}</p>
            {p.is_card_purchase && (
              <Badge variant="neutral">
                <CreditCard className="size-3" />
                Cartão
              </Badge>
            )}
          </div>
          <p className="truncate text-[13px] text-muted">
            {[p.category, p.bank_account, p.counterparty].filter(Boolean).join(' · ') || '—'}
          </p>
        </div>
      ),
    },
    {
      key: 'type',
      header: 'Tipo',
      render: (p) => (p.type === 'receivable' ? <Badge variant="success">A receber</Badge> : <Badge variant="warning">A pagar</Badge>),
    },
    {
      key: 'installments',
      header: 'Parcelas',
      className: 'text-center',
      render: (p) => <Badge variant="neutral">{p.installments_count}×</Badge>,
    },
    {
      key: 'total_value',
      header: 'Valor total',
      render: (p) => (
        <div className="text-right">
          <p className={`font-medium ${p.type === 'receivable' ? 'text-success' : 'text-foreground'}`}>
            {formatCurrency(p.total_value)}
          </p>
        </div>
      ),
    },
    {
      key: 'first_due_date',
      header: 'Início',
      render: (p) => <span className="text-muted">{formatDate(p.first_due_date)}</span>,
    },
    {
      key: 'last_due_date',
      header: 'Última',
      render: (p) => <span className="text-muted">{formatDate(p.last_due_date)}</span>,
    },
    {
      key: 'progress',
      header: 'Progresso',
      render: (p) => {
        const paid = p.settled_count
        const total = p.installments_count
        const remaining = total - paid - p.cancelled_count

        return (
          <div className="flex flex-wrap items-center gap-1.5">
            <Badge variant="success">{paid} pagas</Badge>
            {remaining > 0 && <Badge variant="primary">{remaining} abertas</Badge>}
            {p.cancelled_count > 0 && <Badge variant="neutral">{p.cancelled_count} canceladas</Badge>}
          </div>
        )
      },
    },
  ]

  return (
    <Page>
      <PageHeader
        title="Parcelamentos"
        description="Lançamentos parcelados agrupados por compra ou contrato."
        breadcrumb={[{ label: 'Dashboard', to: '/dashboard' }, { label: 'Parcelamentos' }]}
      />

      <PageContent>
        <FilterBar>
          <SearchInput
            placeholder="Buscar parcelamentos..."
            aria-label="Buscar parcelamentos"
            value={search}
            onChange={(e) => {
              setSearch(e.target.value)
              setSearchParams((p) => {
                e.target.value ? p.set('search', e.target.value) : p.delete('search')
                p.delete('page')
                return p
              }, { replace: true })
            }}
          />
        </FilterBar>

        <DataTable
          caption="Lista de parcelamentos"
          columns={columns}
          rows={query.data?.data ?? []}
          rowKey={(p) => p.id}
          loading={query.isPending}
          onRowClick={(p) => navigate(`/installments/${p.id}`)}
          emptyState={
            <EmptyState
              icon={CalendarRange}
              title="Nenhum parcelamento encontrado"
              description="Parcelamentos são criados ao dividir um lançamento em parcelas."
            />
          }
        />

        {query.data && <Pagination meta={query.data.meta} onPageChange={(next) => setSearchParams((p) => { next > 1 ? p.set('page', String(next)) : p.delete('page'); return p })} />}
      </PageContent>
    </Page>
  )
}
