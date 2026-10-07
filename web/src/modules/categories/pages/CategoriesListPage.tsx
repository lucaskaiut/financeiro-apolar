import { useMemo, useState } from 'react'
import { useNavigate, useSearchParams } from 'react-router'
import { ChevronRight, Pencil, Plus, Tags, Trash2 } from 'lucide-react'
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
import { cn } from '@/shared/utils/cn'
import { useCategoriesQuery, useDeleteCategory } from '../hooks/useCategories'
import { buildCategoryListRows, type CategoryListRow } from '../utils/category-list-tree'

const PER_PAGE = 10
const FETCH_PER_PAGE = 100

export default function CategoriesListPage() {
  const [searchParams, setSearchParams] = useSearchParams()
  const [search, setSearch] = useState(searchParams.get('search') ?? '')
  const debouncedSearch = useDebounce(search)
  const page = Number(searchParams.get('page') ?? 1)

  const navigate = useNavigate()
  const { can } = usePermissions()
  const [toDelete, setToDelete] = useState<Category | null>(null)
  const [collapsedParentIds, setCollapsedParentIds] = useState<Set<string>>(() => new Set())
  const deleteCategory = useDeleteCategory()

  const query = useCategoriesQuery({
    page: 1,
    per_page: FETCH_PER_PAGE,
    search: debouncedSearch || undefined,
  })

  const { rows, pagination } = useMemo(
    () =>
      buildCategoryListRows({
        categories: query.data?.data ?? [],
        search: debouncedSearch || undefined,
        collapsedParentIds,
        page,
        perPage: PER_PAGE,
      }),
    [query.data?.data, debouncedSearch, collapsedParentIds, page],
  )

  const toggleParentCollapsed = (parentId: string) => {
    setCollapsedParentIds((prev) => {
      const next = new Set(prev)
      if (next.has(parentId)) next.delete(parentId)
      else next.add(parentId)
      return next
    })
  }

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

  const columns: Array<Column<CategoryListRow>> = [
    {
      key: 'name',
      header: 'Categoria',
      render: (c) => {
        const isSub = c.depth === 1
        const isCollapsed = collapsedParentIds.has(c.id)

        return (
          <div className={cn('min-w-0', isSub && 'border-l-2 border-surface-3 pl-4')}>
            <div
              className={cn('flex items-center gap-2', isSub ? 'gap-2.5' : 'gap-1.5')}
              style={isSub ? { marginLeft: '0.5rem' } : undefined}
            >
              {!isSub && c.hasChildren ? (
                <button
                  type="button"
                  onClick={() => toggleParentCollapsed(c.id)}
                  aria-expanded={!isCollapsed}
                  aria-label={isCollapsed ? `Expandir subcategorias de ${c.name}` : `Recolher subcategorias de ${c.name}`}
                  className="flex size-7 shrink-0 items-center justify-center rounded-md text-muted transition-colors hover:bg-surface-2 hover:text-foreground"
                >
                  <ChevronRight className={cn('size-4 transition-transform duration-200', !isCollapsed && 'rotate-90')} />
                </button>
              ) : (
                <span className="size-7 shrink-0" aria-hidden />
              )}
              <span className="size-3 shrink-0 rounded-full" style={{ backgroundColor: c.color ?? '#e2e8f0' }} />
              <span className={cn('text-foreground', isSub ? 'text-[13px]' : 'font-medium')}>{c.name}</span>
              {!isSub && c.hasChildren && (
                <Badge variant="neutral" className="tabular-nums">
                  {c.subcategories_count ?? 0}
                </Badge>
              )}
              {isSub && <Badge variant="neutral">Subcategoria</Badge>}
            </div>
          </div>
        )
      },
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
            render: (c: CategoryListRow) => (
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
          } satisfies Column<CategoryListRow>,
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
          rows={rows}
          rowKey={(c) => c.id}
          loading={query.isPending}
          emptyState={
            <EmptyState icon={Tags} title="Nenhuma categoria cadastrada" description="Crie categorias para classificar os lançamentos." />
          }
        />

        {pagination.total > 0 && (
          <Pagination meta={pagination} onPageChange={(next) => updateParams({ page: next })} />
        )}
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
