import { useState } from 'react'
import { useNavigate, useSearchParams } from 'react-router'
import { Pencil, Plus, Tags, Trash2 } from 'lucide-react'
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
import type { Category } from '@/shared/types/models'
import { useCategoriesQuery, useDeleteCategory } from '../hooks/useCategories'

const PER_PAGE = 10

export default function CategoriesListPage() {
  const [searchParams, setSearchParams] = useSearchParams()
  const [search, setSearch] = useState(searchParams.get('search') ?? '')
  const debouncedSearch = useDebounce(search)
  const page = Number(searchParams.get('page') ?? 1)

  const navigate = useNavigate()
  const { can } = usePermissions()
  const [toDelete, setToDelete] = useState<Category | null>(null)
  const deleteCategory = useDeleteCategory()

  const query = useCategoriesQuery({ page, per_page: PER_PAGE, search: debouncedSearch || undefined })

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
    deleteCategory.mutate(toDelete.id, { onSettled: () => setToDelete(null) })
  }

  const canMutate = can(Permission.CATEGORIES_UPDATE) || can(Permission.CATEGORIES_DELETE)

  const columns: Array<Column<Category>> = [
    {
      key: 'name',
      header: 'Categoria',
      render: (c) => (
        <div className="min-w-0">
          <div className="flex items-center gap-2.5">
            <span className="size-3 shrink-0 rounded-full" style={{ backgroundColor: c.color ?? '#e2e8f0' }} />
            <span className="font-medium text-foreground">{c.name}</span>
            {c.parent_name && <Badge variant="neutral">Subcategoria</Badge>}
          </div>
          {c.parent_name && <p className="pl-[22px] text-[13px] text-muted">Subcategoria de {c.parent_name}</p>}
          {!c.parent_name && (c.subcategories_count ?? 0) > 0 && (
            <p className="pl-[22px] text-[13px] text-muted">{c.subcategories_count} subcategoria(s)</p>
          )}
        </div>
      ),
    },
    {
      key: 'type',
      header: 'Tipo',
      render: (c) =>
        c.type === 'income' ? <Badge variant="success">Receita</Badge> : <Badge variant="warning">Despesa</Badge>,
    },
    {
      key: 'status',
      header: 'Status',
      render: (c) => (c.status === 'active' ? <Badge variant="success">Ativo</Badge> : <Badge>Inativo</Badge>),
    },
    ...(canMutate
      ? [
          {
            key: 'actions',
            header: <span className="sr-only">Ações</span>,
            className: 'w-24 text-right',
            render: (c: Category) => (
              <div className="flex items-center justify-end gap-1">
                {can(Permission.CATEGORIES_UPDATE) && (
                  <Button variant="ghost" size="sm" onClick={() => navigate(`/categories/${c.id}/edit`)} aria-label={`Editar ${c.name}`}>
                    <Pencil className="size-4" />
                  </Button>
                )}
                {can(Permission.CATEGORIES_DELETE) && (
                  <Button variant="ghost" size="sm" onClick={() => setToDelete(c)} aria-label={`Excluir ${c.name}`} className="text-danger hover:bg-danger-soft hover:text-danger">
                    <Trash2 className="size-4" />
                  </Button>
                )}
              </div>
            ),
          } satisfies Column<Category>,
        ]
      : []),
  ]

  return (
    <Page>
      <PageHeader
        title="Categorias"
        description="Classifique receitas e despesas da sua operação."
        breadcrumb={[{ label: 'Dashboard', to: '/dashboard' }, { label: 'Categorias' }]}
        actions={
          <Can permission={Permission.CATEGORIES_CREATE}>
            <ButtonLink to="/categories/create">
              <Plus className="size-4" />
              Nova categoria
            </ButtonLink>
          </Can>
        }
      />

      <PageContent>
        <FilterBar>
          <SearchInput
            placeholder="Buscar categorias..."
            aria-label="Buscar categorias"
            value={search}
            onChange={(e) => {
              setSearch(e.target.value)
              updateParams({ search: e.target.value })
            }}
          />
        </FilterBar>

        <DataTable
          caption="Lista de categorias"
          columns={columns}
          rows={query.data?.data ?? []}
          rowKey={(c) => c.id}
          loading={query.isPending}
          emptyState={
            <EmptyState icon={Tags} title="Nenhuma categoria cadastrada" description="Crie categorias para classificar os lançamentos." />
          }
        />

        {query.data && <Pagination meta={query.data.meta} onPageChange={(next) => updateParams({ page: next })} />}
      </PageContent>

      <ConfirmDialog
        open={toDelete !== null}
        onClose={() => setToDelete(null)}
        onConfirm={confirmDelete}
        loading={deleteCategory.isPending}
        title="Excluir categoria"
        description={<>Tem certeza que deseja excluir <strong>{toDelete?.name}</strong>?</>}
        confirmLabel="Excluir"
      />
    </Page>
  )
}
