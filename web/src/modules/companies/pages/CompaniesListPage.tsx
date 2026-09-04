import { useState } from 'react'
import { useNavigate, useSearchParams } from 'react-router'
import { Building2, Pencil, Plus, Trash2 } from 'lucide-react'
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
import type { Company } from '@/shared/types/models'
import { useCompaniesQuery, useDeleteCompany } from '../hooks/useCompanies'

const PER_PAGE = 10

export default function CompaniesListPage() {
  const [searchParams, setSearchParams] = useSearchParams()
  const [search, setSearch] = useState(searchParams.get('search') ?? '')
  const debouncedSearch = useDebounce(search)
  const page = Number(searchParams.get('page') ?? 1)

  const navigate = useNavigate()
  const { can } = usePermissions()
  const [toDelete, setToDelete] = useState<Company | null>(null)
  const deleteCompany = useDeleteCompany()

  const query = useCompaniesQuery({ page, per_page: PER_PAGE, search: debouncedSearch || undefined })

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
    deleteCompany.mutate(toDelete.id, { onSettled: () => setToDelete(null) })
  }

  const canMutate = can(Permission.COMPANIES_UPDATE) || can(Permission.COMPANIES_DELETE)

  const columns: Array<Column<Company>> = [
    {
      key: 'name',
      header: 'Empresa',
      render: (c) => <span className="font-medium text-foreground">{c.name}</span>,
    },
    {
      key: 'status',
      header: 'Status',
      render: (c) =>
        c.status === 'active' ? <Badge variant="success">Ativo</Badge> : <Badge>Inativo</Badge>,
    },
    ...(canMutate
      ? [
          {
            key: 'actions',
            header: <span className="sr-only">Ações</span>,
            className: 'w-24 text-right',
            render: (c: Company) => (
              <div className="flex items-center justify-end gap-1">
                {can(Permission.COMPANIES_UPDATE) && (
                  <Button variant="ghost" size="sm" onClick={() => navigate(`/companies/${c.id}/edit`)} aria-label={`Editar ${c.name}`}>
                    <Pencil className="size-4" />
                  </Button>
                )}
                {can(Permission.COMPANIES_DELETE) && (
                  <Button variant="ghost" size="sm" onClick={() => setToDelete(c)} aria-label={`Excluir ${c.name}`} className="text-danger hover:bg-danger-soft hover:text-danger">
                    <Trash2 className="size-4" />
                  </Button>
                )}
              </div>
            ),
          } satisfies Column<Company>,
        ]
      : []),
  ]

  return (
    <Page>
      <PageHeader
        title="Empresas"
        description="Cadastre empresas para classificação de lançamentos e rateios."
        breadcrumb={[{ label: 'Dashboard', to: '/dashboard' }, { label: 'Empresas' }]}
        actions={
          <Can permission={Permission.COMPANIES_CREATE}>
            <ButtonLink to="/companies/create">
              <Plus className="size-4" />
              Nova empresa
            </ButtonLink>
          </Can>
        }
      />

      <PageContent>
        <FilterBar>
          <SearchInput
            placeholder="Buscar por nome..."
            aria-label="Buscar empresas"
            value={search}
            onChange={(e) => {
              setSearch(e.target.value)
              updateParams({ search: e.target.value })
            }}
          />
        </FilterBar>

        <DataTable
          caption="Lista de empresas"
          columns={columns}
          rows={query.data?.data ?? []}
          rowKey={(c) => c.id}
          loading={query.isPending}
          emptyState={
            <EmptyState
              icon={Building2}
              title="Nenhuma empresa cadastrada"
              description="Cadastre empresas para vincular a lançamentos financeiros."
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
        loading={deleteCompany.isPending}
        title="Excluir empresa"
        description={<>Tem certeza que deseja excluir <strong>{toDelete?.name}</strong>?</>}
        confirmLabel="Excluir"
      />
    </Page>
  )
}
