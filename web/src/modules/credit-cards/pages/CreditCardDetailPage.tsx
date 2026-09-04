import { useState } from 'react'
import { useParams } from 'react-router'
import { CreditCard, FileText } from 'lucide-react'
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
import type { CreditCardInvoice } from '@/shared/types/models'
import { PurchaseForm } from '../forms/PurchaseForm'
import {
  useCloseInvoice,
  useCreatePurchase,
  useCreditCardInvoices,
  useCreditCardQuery,
} from '../hooks/useCreditCards'

export default function CreditCardDetailPage() {
  const { id } = useParams<{ id: string }>()
  const [referenceMonth, setReferenceMonth] = useState('')

  const cardQuery = useCreditCardQuery(id)
  const invoicesQuery = useCreditCardInvoices(id)
  const createPurchase = useCreatePurchase(id ?? '')
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
      render: (invoice) => <Badge variant={invoice.status === 'closed' ? 'success' : 'primary'}>{invoice.status_label}</Badge>,
    },
    {
      key: 'purchases_count',
      header: 'Compras',
      render: (invoice) => <span className="text-muted">{invoice.purchases_count ?? '—'}</span>,
    },
  ]

  return (
    <Page>
      <PageHeader
        title={cardQuery.data?.name ?? 'Cartão de crédito'}
        description={cardQuery.data?.institution ?? 'Gerencie compras e faturas do cartão.'}
        breadcrumb={[
          { label: 'Dashboard', to: '/dashboard' },
          { label: 'Cartões de crédito', to: '/credit-cards' },
          { label: cardQuery.data?.name ?? 'Detalhes' },
        ]}
        actions={
          cardQuery.data ? (
            <Can permission={Permission.CREDIT_CARDS_UPDATE}>
              <ButtonLink to={`/credit-cards/${id}/edit`} variant="secondary">
                Editar cartão
              </ButtonLink>
            </Can>
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
          <>
            <Can permission={Permission.CREDIT_CARDS_UPDATE}>
              <PurchaseForm
                submitting={createPurchase.isPending}
                onSubmit={(payload) => createPurchase.mutateAsync(payload)}
              />
            </Can>

            <Card>
              <CardContent>
                <div className="mb-4 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                  <div>
                    <h2 className="flex items-center gap-2 text-sm font-semibold text-foreground">
                      <FileText className="size-4 text-muted" />
                      Faturas
                    </h2>
                    <p className="mt-1 text-[13px] text-muted">Feche a fatura do mês para gerar a conta a pagar.</p>
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
                    </div>
                  </Can>
                </div>

                <DataTable
                  caption="Faturas do cartão"
                  columns={invoiceColumns}
                  rows={invoicesQuery.data ?? []}
                  rowKey={(invoice) => invoice.id}
                  loading={invoicesQuery.isPending}
                  emptyState={<EmptyState icon={FileText} title="Nenhuma fatura" description="Registre compras e feche a fatura do mês." />}
                />
              </CardContent>
            </Card>
          </>
        )}
      </PageContent>
    </Page>
  )
}
