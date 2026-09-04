import { useState } from 'react'
import { useNavigate, useSearchParams } from 'react-router'
import { Landmark, Pencil, Plus, Trash2 } from 'lucide-react'
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
import { formatCurrency } from '@/shared/utils/format'
import type { CostCenter } from '@/shared/types/models'
import { useCostCentersQuery, useDeleteCostCenter } from '../hooks/useCostCenters'

const PER_PAGE = 10

export default function CostCentersListPage() {
  const [searchParams, setSearchParams] = useSearchParams()
  const [search, setSearch] = useState(searchParams.get('search') ?? '')
  const debouncedSearch = useDebounce(search)
  const page = Number(searchParams.get('page') ?? 1)

  const navigate = useNavigate()
  const { can } = usePermissions()
  const [toDelete, setToDelete] = useState<CostCenter | null>(null)
  const deleteCostCenter = useDeleteCostCenter()

  const query = useCostCentersQuery({ page, per_page: PER_PAGE, search: debouncedSearch || undefined })

  const updateParams = (next: { page?: number; search?: string }) => {
    setSearchParams((params) => {
      if (next.search !== undefined) {
        next.search ? params.set('search', next.search) : params.delete('search')
        params.delete('page')
      }
      if (next.page !== undefined) {
        next.page > 1 ? params.set('page', String(next.page)) : params.delete('page')
      }
      return params
    }, { replace: true })
  }

  const confirmDelete = () => {
    if (!toDelete) return
    deleteCostCenter.mutate(toDelete.id, { onSettled: () => setToDelete(null) })
  }

  const canMutate = can(Permission.COST_CENTERS_UPDATE) || can(Permission.COST_CENTERS_DELETE)

  const columns: Array<Column<CostCenter>> = [
    {
      key: 'name',
      header: 'Centro de custo',
      render: (cc) => (
        <div className="min-w-0">
          <p className="font-medium text-foreground">{cc.name}</p>
          <p className="text-[13px] text-muted">
            {[cc.bank, cc.agency, cc.account].filter(Boolean).join(' · ') || '—'}
          </p>
        </div>
      ),
    },
    {
      key: 'type',
      header: 'Tipo',
      render: (cc) => <span className="text-muted">{cc.type_label}</span>,
    },
    {
      key: 'initial_balance',
      header: 'Saldo inicial',
      render: (cc) => <span className="text-muted">{formatCurrency(cc.initial_balance)}</span>,
    },
    {
      key: 'status',
      header: 'Status',
      render: (cc) =>
        cc.status === 'active' ? <Badge variant="success">Ativo</Badge> : <Badge>Inativo</Badge>,
    },
    ...(canMutate
      ? [
          {
            key: 'actions',
            header: <span className="sr-only">Ações</span>,
            className: 'w-24 text-right',
            render: (cc: CostCenter) => (
              <div className="flex items-center justify-end gap-1">
                {can(Permission.COST_CENTERS_UPDATE) && (
                  <Button variant="ghost" size="sm" onClick={() => navigate(`/cost-centers/${cc.id}/edit`)} aria-label={`Editar ${cc.name}`}>
                    <Pencil className="size-4" />
                  </Button>
                )}
                {can(Permission.COST_CENTERS_DELETE) && (
                  <Button variant="ghost" size="sm" onClick={() => setToDelete(cc)} aria-label={`Excluir ${cc.name}`} className="text-danger hover:bg-danger-soft hover:text-danger">
                    <Trash2 className="size-4" />
                  </Button>
                )}
              </div>
            ),
          } satisfies Column<CostCenter>,
        ]
      : []),
  ]

  return (
    <Page>
      <PageHeader
        title="Centros de custo"
        description="Cada centro de custo representa uma conta bancária operacional."
        breadcrumb={[{ label: 'Dashboard', to: '/dashboard' }, { label: 'Centros de custo' }]}
        actions={
          <Can permission={Permission.COST_CENTERS_CREATE}>
            <ButtonLink to="/cost-centers/create">
              <Plus className="size-4" />
              Novo centro de custo
            </ButtonLink>
          </Can>
        }
      />

      <PageContent>
        <FilterBar>
          <SearchInput
            placeholder="Buscar por nome, banco ou conta..."
            aria-label="Buscar centros de custo"
            value={search}
            onChange={(e) => {
              setSearch(e.target.value)
              updateParams({ search: e.target.value })
            }}
          />
        </FilterBar>

        <DataTable
          caption="Lista de centros de custo"
          columns={columns}
          rows={query.data?.data ?? []}
          rowKey={(cc) => cc.id}
          loading={query.isPending}
          emptyState={
            <EmptyState
              icon={Landmark}
              title="Nenhum centro de custo cadastrado"
              description="Cadastre uma conta bancária para começar a movimentar."
            />
          }
        />

        {query.data && (
          <Pagination meta={query.data.meta} onPageChange={(next) => updateParams({ page: next })} />
        )}
      </PageContent>

      <ConfirmDialog
        open={toDelete !== null}
        onClose={() => setToDelete(null)}
        onConfirm={confirmDelete}
        loading={deleteCostCenter.isPending}
        title="Excluir centro de custo"
        description={<>Tem certeza que deseja excluir <strong>{toDelete?.name}</strong>?</>}
        confirmLabel="Excluir"
      />
    </Page>
  )
}
