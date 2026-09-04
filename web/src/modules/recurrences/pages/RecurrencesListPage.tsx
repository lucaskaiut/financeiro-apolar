import { useState } from 'react'
import { useNavigate, useSearchParams } from 'react-router'
import { Pencil, Plus, Repeat, Trash2, Copy } from 'lucide-react'
import {
  Badge,
  Button,
  ButtonLink,
  ConfirmDialog,
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
import { Can } from '@/app/guards/PermissionGuard'
import { Permission } from '@/shared/constants/permissions'
import { usePermissions } from '@/shared/hooks/usePermissions'
import { useDebounce } from '@/shared/hooks/useDebounce'
import { formatCurrency, formatDate } from '@/shared/utils/format'
import type { Recurrence } from '@/shared/types/models'
import { useDeleteRecurrence, useRecurrencesQuery } from '../hooks/useRecurrences'

const PER_PAGE = 10

export default function RecurrencesListPage() {
  const [searchParams, setSearchParams] = useSearchParams()
  const [search, setSearch] = useState(searchParams.get('search') ?? '')
  const debouncedSearch = useDebounce(search)
  const page = Number(searchParams.get('page') ?? 1)

  const navigate = useNavigate()
  const { can } = usePermissions()
  const [toDelete, setToDelete] = useState<Recurrence | null>(null)
  const deleteRecurrence = useDeleteRecurrence()

  const query = useRecurrencesQuery({ page, per_page: PER_PAGE, search: debouncedSearch || undefined })

  const canMutate = can(Permission.RECURRENCES_UPDATE) || can(Permission.RECURRENCES_DELETE) || can(Permission.RECURRENCES_CREATE)

  const columns: Array<Column<Recurrence>> = [
    {
      key: 'description',
      header: 'Recorrência',
      render: (r) => (
        <div className="min-w-0">
          <p className="truncate font-medium text-foreground">{r.description}</p>
          <p className="truncate text-[13px] text-muted">{r.counterparty ?? r.cost_center ?? '—'}</p>
        </div>
      ),
    },
    {
      key: 'type',
      header: 'Tipo',
      render: (r) => (r.type === 'receivable' ? <Badge variant="success">Receita</Badge> : <Badge variant="warning">Despesa</Badge>),
    },
    {
      key: 'value',
      header: 'Valor',
      render: (r) => <span className="text-muted">{formatCurrency(r.value)}</span>,
    },
    {
      key: 'frequency',
      header: 'Frequência',
      render: (r) => <span className="text-muted">{r.frequency_label}</span>,
    },
    {
      key: 'start_date',
      header: 'Início',
      render: (r) => <span className="text-muted">{formatDate(r.start_date)}</span>,
    },
    {
      key: 'occurrences',
      header: 'Ocorrências',
      render: (r) => <Badge variant="neutral">{r.occurrences_count}</Badge>,
    },
    ...(canMutate
      ? [
          {
            key: 'actions',
            header: <span className="sr-only">Ações</span>,
            className: 'w-32 text-right',
            render: (r: Recurrence) => (
              <div className="flex items-center justify-end gap-1">
                {can(Permission.RECURRENCES_CREATE) && (
                  <Button variant="ghost" size="sm" onClick={() => navigate(`/recurrences/create?clone=${r.id}`)} aria-label={`Clonar ${r.description}`}>
                    <Copy className="size-4" />
                  </Button>
                )}
                {can(Permission.RECURRENCES_UPDATE) && (
                  <Button variant="ghost" size="sm" onClick={() => navigate(`/recurrences/${r.id}/edit`)} aria-label={`Editar ${r.description}`}>
                    <Pencil className="size-4" />
                  </Button>
                )}
                {can(Permission.RECURRENCES_DELETE) && (
                  <Button variant="ghost" size="sm" onClick={() => setToDelete(r)} aria-label={`Excluir ${r.description}`} className="text-danger hover:bg-danger-soft hover:text-danger">
                    <Trash2 className="size-4" />
                  </Button>
                )}
              </div>
            ),
          } satisfies Column<Recurrence>,
        ]
      : []),
  ]

  return (
    <Page>
      <PageHeader
        title="Recorrências"
        description="Lançamentos que se repetem automaticamente."
        breadcrumb={[{ label: 'Dashboard', to: '/dashboard' }, { label: 'Recorrências' }]}
        actions={
          <Can permission={Permission.RECURRENCES_CREATE}>
            <ButtonLink to="/recurrences/create">
              <Plus className="size-4" />
              Nova recorrência
            </ButtonLink>
          </Can>
        }
      />

      <PageContent>
        <FilterBar>
          <SearchInput
            placeholder="Buscar recorrências..."
            aria-label="Buscar recorrências"
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
          caption="Lista de recorrências"
          columns={columns}
          rows={query.data?.data ?? []}
          rowKey={(r) => r.id}
          loading={query.isPending}
          emptyState={<EmptyState icon={Repeat} title="Nenhuma recorrência" description="Crie recorrências para automatizar lançamentos." />}
        />

        {query.data && <Pagination meta={query.data.meta} onPageChange={(next) => setSearchParams((p) => { next > 1 ? p.set('page', String(next)) : p.delete('page'); return p })} />}
      </PageContent>

      <ConfirmDialog
        open={toDelete !== null}
        onClose={() => setToDelete(null)}
        onConfirm={() => { if (toDelete) deleteRecurrence.mutate(toDelete.id, { onSettled: () => setToDelete(null) }) }}
        loading={deleteRecurrence.isPending}
        title="Excluir recorrência"
        description={<>Tem certeza que deseja excluir <strong>{toDelete?.description}</strong>?</>}
        confirmLabel="Excluir"
      />
    </Page>
  )
}
