import { useState } from 'react'
import { useNavigate, useSearchParams } from 'react-router'
import { CreditCard, Eye, Pencil, Plus, Trash2 } from 'lucide-react'
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
import type { CreditCard as CreditCardModel } from '@/shared/types/models'
import { useCreditCardsQuery, useDeleteCreditCard } from '../hooks/useCreditCards'

const PER_PAGE = 10

export default function CreditCardsListPage() {
  const [searchParams, setSearchParams] = useSearchParams()
  const [search, setSearch] = useState(searchParams.get('search') ?? '')
  const debouncedSearch = useDebounce(search)
  const page = Number(searchParams.get('page') ?? 1)

  const navigate = useNavigate()
  const { can } = usePermissions()
  const [toDelete, setToDelete] = useState<CreditCardModel | null>(null)
  const deleteCreditCard = useDeleteCreditCard()

  const query = useCreditCardsQuery({ page, per_page: PER_PAGE, search: debouncedSearch || undefined })

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
    deleteCreditCard.mutate(toDelete.id, { onSettled: () => setToDelete(null) })
  }


  const columns: Array<Column<CreditCardModel>> = [
    {
      key: 'name',
      header: 'Cartão',
      render: (card) => (
        <div className="min-w-0">
          <p className="font-medium text-foreground">{card.name}</p>
          <p className="text-[13px] text-muted">{card.institution ?? card.bank_account ?? '—'}</p>
        </div>
      ),
    },
    {
      key: 'limit',
      header: 'Limite',
      render: (card) => <span className="text-muted">{card.limit != null ? formatCurrency(card.limit) : '—'}</span>,
    },
    {
      key: 'closing_day',
      header: 'Fechamento',
      render: (card) => <span className="text-muted">{card.closing_day ? `Dia ${card.closing_day}` : '—'}</span>,
    },
    {
      key: 'status',
      header: 'Status',
      render: (card) =>
        card.status === 'active' ? <Badge variant="success">Ativo</Badge> : <Badge>Inativo</Badge>,
    },
    {
      key: 'actions',
      header: <span className="sr-only">Ações</span>,
      className: 'w-32 text-right',
      render: (card) => (
        <div className="flex items-center justify-end gap-1">
          <Button variant="ghost" size="sm" onClick={() => navigate(`/credit-cards/${card.id}`)} aria-label={`Ver ${card.name}`}>
            <Eye className="size-4" />
          </Button>
          {can(Permission.CREDIT_CARDS_UPDATE) && (
            <Button variant="ghost" size="sm" onClick={() => navigate(`/credit-cards/${card.id}/edit`)} aria-label={`Editar ${card.name}`}>
              <Pencil className="size-4" />
            </Button>
          )}
          {can(Permission.CREDIT_CARDS_DELETE) && (
            <Button variant="ghost" size="sm" onClick={() => setToDelete(card)} aria-label={`Excluir ${card.name}`} className="text-danger hover:bg-danger-soft hover:text-danger">
              <Trash2 className="size-4" />
            </Button>
          )}
        </div>
      ),
    },
  ]

  return (
    <Page>
      <PageHeader
        title="Cartões de crédito"
        description="Gerencie cartões, compras e fechamento de faturas."
        breadcrumb={[{ label: 'Dashboard', to: '/dashboard' }, { label: 'Cartões de crédito' }]}
        actions={
          <Can permission={Permission.CREDIT_CARDS_CREATE}>
            <ButtonLink to="/credit-cards/create">
              <Plus className="size-4" />
              Novo cartão
            </ButtonLink>
          </Can>
        }
      />

      <PageContent>
        <FilterBar>
          <SearchInput
            placeholder="Buscar por nome ou instituição..."
            aria-label="Buscar cartões"
            value={search}
            onChange={(e) => {
              setSearch(e.target.value)
              updateParams({ search: e.target.value })
            }}
          />
        </FilterBar>

        <DataTable
          caption="Lista de cartões de crédito"
          columns={columns}
          rows={query.data?.data ?? []}
          rowKey={(card) => card.id}
          loading={query.isPending}
          emptyState={
            <EmptyState
              icon={CreditCard}
              title="Nenhum cartão cadastrado"
              description="Cadastre um cartão de crédito para registrar compras e fechar faturas."
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
        loading={deleteCreditCard.isPending}
        title="Excluir cartão"
        description={<>Tem certeza que deseja excluir <strong>{toDelete?.name}</strong>?</>}
        confirmLabel="Excluir"
      />
    </Page>
  )
}
