import { useState } from 'react'
import { useParams } from 'react-router'
import { CreditCard, FileText, Upload } from 'lucide-react'
import {
  Badge,
  Button,
  ButtonLink,
  Card,
  CardContent,
  DataTable,
  EmptyState,
  Page,
  PageContent,
  PageHeader,
  Skeleton,
  type Column,
} from '@/shared/design-system'
import { Can } from '@/app/guards/PermissionGuard'
import { Permission } from '@/shared/constants/permissions'
import { formatCurrency, formatDate } from '@/shared/utils/format'
import type { Account, CreditCardInvoice } from '@/shared/types/models'
import {
  useCloseInvoice,
  useCreditCardInvoices,
  useCreditCardQuery,
} from '../hooks/useCreditCards'
import { ImportInvoiceDialog } from '../components/ImportInvoiceDialog'

export default function CreditCardDetailPage() {
  const { id } = useParams<{ id: string }>()
  const [referenceMonth, setReferenceMonth] = useState('')
  const [expandedInvoiceId, setExpandedInvoiceId] = useState<string | null>(null)
  const [importOpen, setImportOpen] = useState(false)

  const cardQuery = useCreditCardQuery(id)
  const invoicesQuery = useCreditCardInvoices(id)
  const closeInvoice = useCloseInvoice(id ?? '')

  const invoiceColumns: Array<Column<CreditCardInvoice>> = [
    {
      key: 'reference_month',
      header: 'Referência',
      render: (invoice) => <span className="font-medium text-foreground">{invoice.reference_month}</span>,
    },
    {
      key: 'total_value',
      header: 'Total',
      render: (invoice) => <span className="text-muted">{formatCurrency(invoice.total_value)}</span>,
    },
    {
      key: 'due_date',
      header: 'Vencimento',
      render: (invoice) => <span className="text-muted">{invoice.due_date ? formatDate(invoice.due_date) : '—'}</span>,
    },
    {
      key: 'status',
      header: 'Status',
      render: (invoice) => (
        <Badge variant={invoice.status === 'paid' ? 'success' : 'primary'}>
          {invoice.status_label}
        </Badge>
      ),
    },
    {
      key: 'purchases_count',
      header: 'Compras',
      render: (invoice) => <span className="text-muted">{invoice.purchases_count ?? invoice.purchases?.length ?? '—'}</span>,
    },
    {
      key: 'actions',
      header: '',
      className: 'w-40 text-right',
      render: (invoice) => (
        <div className="flex items-center justify-end gap-1">
          <Button
            variant="ghost"
            size="sm"
            onClick={() => setExpandedInvoiceId((current) => (current === invoice.id ? null : invoice.id))}
          >
            {expandedInvoiceId === invoice.id ? 'Ocultar' : 'Compras'}
          </Button>
          {invoice.financial_account_id && (
            <ButtonLink to={`/accounts/${invoice.financial_account_id}/edit`} variant="ghost" size="sm">
              Fatura
            </ButtonLink>
          )}
        </div>
      ),
    },
  ]

  const purchaseColumns: Array<Column<Account>> = [
    {
      key: 'description',
      header: 'Descrição',
      render: (purchase) => (
        <div>
          <p className="font-medium text-foreground">{purchase.description}</p>
          {purchase.purchase_date && (
            <p className="text-[12px] text-muted">Compra em {formatDate(purchase.purchase_date)}</p>
          )}
        </div>
      ),
    },
    {
      key: 'cost_center',
      header: 'Centro de custo',
      render: (purchase) => <span className="text-muted">{purchase.cost_center ?? '—'}</span>,
    },
    {
      key: 'category',
      header: 'Categoria',
      render: (purchase) => <span className="text-muted">{purchase.category?.name ?? '—'}</span>,
    },
    {
      key: 'value',
      header: 'Valor',
      render: (purchase) => <span className="text-muted tabular-nums">{formatCurrency(purchase.value)}</span>,
    },
    {
      key: 'status',
      header: 'Status',
      render: (purchase) => <Badge variant={purchase.status === 'settled' ? 'success' : 'primary'}>{purchase.status_label}</Badge>,
    },
  ]

  const expandedInvoice = (invoicesQuery.data ?? []).find((invoice) => invoice.id === expandedInvoiceId)

  return (
    <Page>
      <PageHeader
        title={cardQuery.data?.name ?? 'Cartão de crédito'}
        description={cardQuery.data?.institution ?? 'Gerencie faturas do cartão.'}
        breadcrumb={[
          { label: 'Dashboard', to: '/dashboard' },
          { label: 'Cartões de crédito', to: '/credit-cards' },
          { label: cardQuery.data?.name ?? 'Detalhes' },
        ]}
        actions={
          cardQuery.data ? (
            <div className="flex flex-wrap gap-2">
              <Can permission={Permission.ACCOUNTS_CREATE}>
                <ButtonLink to={`/accounts/create?credit_card_id=${id}`}>
                  Nova compra
                </ButtonLink>
              </Can>
              <Can permission={Permission.CREDIT_CARDS_UPDATE}>
                <ButtonLink to={`/credit-cards/${id}/edit`} variant="secondary">
                  Editar cartão
                </ButtonLink>
              </Can>
            </div>
          ) : undefined
        }
      />

      <PageContent className="space-y-6">
        {cardQuery.isPending && (
          <Card>
            <Skeleton className="h-48 w-full" />
          </Card>
        )}

        {cardQuery.isError && (
          <Card>
            <EmptyState
              icon={CreditCard}
              title="Cartão não encontrado"
              action={<ButtonLink to="/credit-cards" variant="secondary">Voltar</ButtonLink>}
            />
          </Card>
        )}

        {cardQuery.data && (
          <Card>
            <CardContent>
              <div className="mb-4 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                <div>
                  <h2 className="flex items-center gap-2 text-sm font-semibold text-foreground">
                    <FileText className="size-4 text-muted" />
                    Faturas
                  </h2>
                  <p className="mt-1 text-[13px] text-muted">
                    O fechamento gera uma única conta a pagar para conciliação. As compras permanecem com centro de custo e categoria e são liquidadas quando a fatura for paga.
                  </p>
                </div>

                <Can permission={Permission.CREDIT_CARDS_UPDATE}>
                  <div className="flex flex-col gap-2 sm:flex-row sm:items-end">
                    <label className="block w-44">
                      <span className="mb-1.5 block text-[13px] font-medium text-foreground">Mês de referência</span>
                      <input
                        type="month"
                        value={referenceMonth}
                        onChange={(e) => setReferenceMonth(e.target.value)}
                        className="h-10 w-full rounded-lg border border-surface-3 bg-surface-1 px-3 text-sm text-foreground"
                      />
                    </label>
                    <Button
                      onClick={() => referenceMonth && closeInvoice.mutate(referenceMonth)}
                      loading={closeInvoice.isPending}
                      disabled={!referenceMonth}
                    >
                      Fechar fatura
                    </Button>
                    <Button variant="secondary" onClick={() => setImportOpen(true)}>
                      <Upload className="size-4" />
                      Importar fatura
                    </Button>
                  </div>
                </Can>
              </div>

              <DataTable
                caption="Faturas do cartão"
                columns={invoiceColumns}
                rows={invoicesQuery.data ?? []}
                rowKey={(invoice) => invoice.id}
                loading={invoicesQuery.isPending}
                emptyState={
                  <EmptyState
                    icon={FileText}
                    title="Nenhuma fatura"
                    description="Lance compras em Contas a pagar/receber com este cartão e feche a fatura do mês."
                    action={
                      <ButtonLink to={`/accounts/create?credit_card_id=${id}`} variant="secondary">
                        Nova compra
                      </ButtonLink>
                    }
                  />
                }
              />

              {expandedInvoice && (
                <div className="mt-6 border-t border-surface-3 pt-4">
                  <h3 className="mb-3 text-sm font-semibold text-foreground">
                    Compras da fatura {expandedInvoice.reference_month}
                  </h3>
                  <DataTable
                    caption={`Compras da fatura ${expandedInvoice.reference_month}`}
                    columns={purchaseColumns}
                    rows={expandedInvoice.purchases ?? []}
                    rowKey={(purchase) => purchase.id}
                    emptyState={
                      <EmptyState
                        icon={FileText}
                        title="Nenhuma compra"
                        description="Esta fatura não possui compras vinculadas."
                      />
                    }
                  />
                </div>
              )}
            </CardContent>
          </Card>
        )}
      </PageContent>

      <ImportInvoiceDialog cardId={id ?? ''} open={importOpen} onClose={() => setImportOpen(false)} />
    </Page>
  )
}
