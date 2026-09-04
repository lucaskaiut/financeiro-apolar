import { useState } from 'react'
import { useSearchParams } from 'react-router'
import { ArrowRightLeft, Plus, Trash2 } from 'lucide-react'
import {
  Button,
  ButtonLink,
  ConfirmDialog,
  DataTable,
  EmptyState,
  Page,
  PageContent,
  PageHeader,
  Pagination,
  type Column,
} from '@/shared/design-system'
import { Can } from '@/app/guards/PermissionGuard'
import { Permission } from '@/shared/constants/permissions'
import { usePermissions } from '@/shared/hooks/usePermissions'
import { formatCurrency, formatDate } from '@/shared/utils/format'
import type { Transfer } from '@/shared/types/models'
import { useDeleteTransfer, useTransfersQuery } from '../hooks/useTransfers'

const PER_PAGE = 10

export default function TransfersListPage() {
  const [searchParams, setSearchParams] = useSearchParams()
  const page = Number(searchParams.get('page') ?? 1)
  const { can } = usePermissions()

  const [toDelete, setToDelete] = useState<Transfer | null>(null)
  const deleteTransfer = useDeleteTransfer()

  const query = useTransfersQuery({ page, per_page: PER_PAGE })

  const columns: Array<Column<Transfer>> = [
    {
      key: 'date',
      header: 'Data',
      render: (t) => <span className="text-muted">{formatDate(t.date)}</span>,
    },
    {
      key: 'from',
      header: 'De',
      render: (t) => <span className="font-medium text-foreground">{t.from_cost_center ?? '—'}</span>,
    },
    {
      key: 'to',
      header: 'Para',
      render: (t) => <span className="font-medium text-foreground">{t.to_cost_center ?? '—'}</span>,
    },
    {
      key: 'value',
      header: 'Valor',
      render: (t) => <span className="font-medium text-foreground">{formatCurrency(t.value)}</span>,
    },
    {
      key: 'description',
      header: 'Descrição',
      render: (t) => <span className="text-muted">{t.description ?? '—'}</span>,
    },
    ...(can(Permission.TRANSFERS_DELETE)
      ? [
          {
            key: 'actions',
            header: <span className="sr-only">Ações</span>,
            className: 'w-16 text-right',
            render: (t: Transfer) => (
              <Button variant="ghost" size="sm" onClick={() => setToDelete(t)} aria-label="Excluir transferência" className="text-danger hover:bg-danger-soft hover:text-danger">
                <Trash2 className="size-4" />
              </Button>
            ),
          } satisfies Column<Transfer>,
        ]
      : []),
  ]

  return (
    <Page>
      <PageHeader
        title="Transferências"
        description="Movimente valores entre centros de custo."
        breadcrumb={[{ label: 'Dashboard', to: '/dashboard' }, { label: 'Transferências' }]}
        actions={
          <Can permission={Permission.TRANSFERS_CREATE}>
            <ButtonLink to="/transfers/create">
              <Plus className="size-4" />
              Nova transferência
            </ButtonLink>
          </Can>
        }
      />

      <PageContent>
        <DataTable
          caption="Lista de transferências"
          columns={columns}
          rows={query.data?.data ?? []}
          rowKey={(t) => t.id}
          loading={query.isPending}
          emptyState={<EmptyState icon={ArrowRightLeft} title="Nenhuma transferência" description="Transfira valores entre as contas da empresa." />}
        />

        {query.data && <Pagination meta={query.data.meta} onPageChange={(next) => setSearchParams((p) => { next > 1 ? p.set('page', String(next)) : p.delete('page'); return p })} />}
      </PageContent>

      <ConfirmDialog
        open={toDelete !== null}
        onClose={() => setToDelete(null)}
        onConfirm={() => { if (toDelete) deleteTransfer.mutate(toDelete.id, { onSettled: () => setToDelete(null) }) }}
        loading={deleteTransfer.isPending}
        title="Excluir transferência"
        description="Os movimentos gerados por esta transferência também serão removidos."
        confirmLabel="Excluir"
      />
    </Page>
  )
}
