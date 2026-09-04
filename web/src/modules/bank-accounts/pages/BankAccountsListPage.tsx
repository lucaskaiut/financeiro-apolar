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
import type { BankAccount } from '@/shared/types/models'
import { useBankAccountsQuery, useDeleteBankAccount } from '../hooks/useBankAccounts'

const PER_PAGE = 10

export default function BankAccountsListPage() {
  const [searchParams, setSearchParams] = useSearchParams()
  const [search, setSearch] = useState(searchParams.get('search') ?? '')
  const debouncedSearch = useDebounce(search)
  const page = Number(searchParams.get('page') ?? 1)

  const navigate = useNavigate()
  const { can } = usePermissions()
  const [toDelete, setToDelete] = useState<BankAccount | null>(null)
  const deleteBankAccount = useDeleteBankAccount()

  const query = useBankAccountsQuery({ page, per_page: PER_PAGE, search: debouncedSearch || undefined })

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
    deleteBankAccount.mutate(toDelete.id, { onSettled: () => setToDelete(null) })
  }

  const canMutate = can(Permission.BANK_ACCOUNTS_UPDATE) || can(Permission.BANK_ACCOUNTS_DELETE)

  const columns: Array<Column<BankAccount>> = [
    {
      key: 'name',
      header: 'Conta bancária',
      render: (ba) => (
        <div className="min-w-0">
          <p className="font-medium text-foreground">{ba.name}</p>
          <p className="text-[13px] text-muted">
            {[ba.bank, ba.agency, ba.account].filter(Boolean).join(' · ') || '—'}
          </p>
        </div>
      ),
    },
    {
      key: 'type',
      header: 'Tipo',
      render: (ba) => <span className="text-muted">{ba.type_label}</span>,
    },
    {
      key: 'initial_balance',
      header: 'Saldo inicial',
      render: (ba) => <span className="text-muted">{formatCurrency(ba.initial_balance)}</span>,
    },
    {
      key: 'status',
      header: 'Status',
      render: (ba) =>
        ba.status === 'active' ? <Badge variant="success">Ativo</Badge> : <Badge>Inativo</Badge>,
    },
    ...(canMutate
      ? [
          {
            key: 'actions',
            header: <span className="sr-only">Ações</span>,
            className: 'w-24 text-right',
            render: (ba: BankAccount) => (
              <div className="flex items-center justify-end gap-1">
                {can(Permission.BANK_ACCOUNTS_UPDATE) && (
                  <Button variant="ghost" size="sm" onClick={() => navigate(`/bank-accounts/${ba.id}/edit`)} aria-label={`Editar ${ba.name}`}>
                    <Pencil className="size-4" />
                  </Button>
                )}
                {can(Permission.BANK_ACCOUNTS_DELETE) && (
                  <Button variant="ghost" size="sm" onClick={() => setToDelete(ba)} aria-label={`Excluir ${ba.name}`} className="text-danger hover:bg-danger-soft hover:text-danger">
                    <Trash2 className="size-4" />
                  </Button>
                )}
              </div>
            ),
          } satisfies Column<BankAccount>,
        ]
      : []),
  ]

  return (
    <Page>
      <PageHeader
        title="Contas bancárias"
        description="Cadastre as contas bancárias operacionais da organização."
        breadcrumb={[{ label: 'Dashboard', to: '/dashboard' }, { label: 'Contas bancárias' }]}
        actions={
          <Can permission={Permission.BANK_ACCOUNTS_CREATE}>
            <ButtonLink to="/bank-accounts/create">
              <Plus className="size-4" />
              Nova conta bancária
            </ButtonLink>
          </Can>
        }
      />

      <PageContent>
        <FilterBar>
          <SearchInput
            placeholder="Buscar por nome, banco ou conta..."
            aria-label="Buscar contas bancárias"
            value={search}
            onChange={(e) => {
              setSearch(e.target.value)
              updateParams({ search: e.target.value })
            }}
          />
        </FilterBar>

        <DataTable
          caption="Lista de contas bancárias"
          columns={columns}
          rows={query.data?.data ?? []}
          rowKey={(ba) => ba.id}
          loading={query.isPending}
          emptyState={
            <EmptyState
              icon={Landmark}
              title="Nenhuma conta bancária cadastrada"
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
        loading={deleteBankAccount.isPending}
        title="Excluir conta bancária"
        description={<>Tem certeza que deseja excluir <strong>{toDelete?.name}</strong>?</>}
        confirmLabel="Excluir"
      />
    </Page>
  )
}
