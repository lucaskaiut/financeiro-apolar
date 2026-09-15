import { useNavigate, useParams } from 'react-router'
import { CalendarRange, CreditCard, Pencil } from 'lucide-react'
import {
  Badge,
  Button,
  Card,
  CardContent,
  CardHeader,
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
import type { Account } from '@/shared/types/models'
import { useInstallmentQuery } from '../hooks/useInstallments'

const STATUS_LABELS: Record<Account['status'], { variant: 'neutral' | 'primary' | 'success' | 'warning' | 'danger'; label: string }> = {
  open: { variant: 'primary', label: 'Aberto' },
  partial: { variant: 'warning', label: 'Parcial' },
  settled: { variant: 'success', label: 'Liquidado' },
  cancelled: { variant: 'neutral', label: 'Cancelado' },
}

export default function InstallmentDetailPage() {
  const { id } = useParams<{ id: string }>()
  const navigate = useNavigate()

  const query = useInstallmentQuery(id)

  const data = query.data

  const columns: Array<Column<Account>> = [
    {
      key: 'installment_number',
      header: 'Parcela',
      className: 'text-center',
      render: (a) => <Badge variant="neutral">{a.installment_number}/{a.installment_total}</Badge>,
    },
    {
      key: 'due_date',
      header: 'Vencimento',
      render: (a) => <span className="text-muted">{formatDate(a.due_date)}</span>,
    },
    {
      key: 'value',
      header: 'Valor',
      className: 'text-right',
      render: (a) => <span className="font-medium text-foreground">{formatCurrency(a.value)}</span>,
    },
    {
      key: 'paid_date',
      header: 'Data da baixa',
      className: 'text-center',
      render: (a) => <span className="inline-block text-muted tabular-nums">{formatDate(a.paid_date)}</span>,
    },
    {
      key: 'status',
      header: 'Status',
      className: 'text-center',
      render: (a) => {
        const s = STATUS_LABELS[a.status]
        return (
          <span className="inline-flex justify-center">
            <Badge variant={s.variant}>{s.label}</Badge>
          </span>
        )
      },
    },
    {
      key: 'actions',
      header: 'Ações',
      className: 'w-20 text-right',
      render: (a: Account) => (
        <div className="flex items-center justify-end gap-1">
          <Can permission={Permission.ACCOUNTS_UPDATE}>
            <Button variant="ghost" size="sm" onClick={() => navigate(`/accounts/${a.id}/edit`)} aria-label={`Editar parcela ${a.installment_number}`}>
              <Pencil className="size-4" />
            </Button>
          </Can>
        </div>
      ),
    },
  ]

  return (
    <Page>
      <PageHeader
        title={data ? data.description : 'Parcelamento'}
        breadcrumb={[
          { label: 'Dashboard', to: '/dashboard' },
          { label: 'Parcelamentos', to: '/installments' },
          { label: data?.description ?? 'Detalhes' },
        ]}
        actions={
          data ? (
            <Can permission={Permission.ACCOUNTS_UPDATE}>
              <Button variant="secondary" onClick={() => navigate(`/installments/${data.id}/edit`)}>
                <Pencil className="size-4" />
                Editar parcelamento
              </Button>
            </Can>
          ) : undefined
        }
      />

      <PageContent>
        {query.isPending && (
          <Card>
            <Skeleton className="h-96 w-full" />
          </Card>
        )}

        {query.isError && (
          <Card>
            <EmptyState icon={CalendarRange} title="Parcelamento não encontrado" />
          </Card>
        )}

        {data && (
          <>
            <Card className="mb-4">
              <CardHeader
                title={data.description}
                description={[data.category, data.bank_account, data.counterparty].filter(Boolean).join(' · ') || undefined}
                actions={
                  <div className="flex items-center gap-2">
                    {data.is_card_purchase && (
                      <Badge variant="neutral">
                        <CreditCard className="size-3" />
                        Cartão
                      </Badge>
                    )}
                    <Badge variant={data.type === 'receivable' ? 'success' : 'warning'}>{data.type_label}</Badge>
                  </div>
                }
              />
              <CardContent>
                <div className="grid grid-cols-2 gap-4 sm:grid-cols-4">
                  <div>
                    <p className="text-[13px] text-muted">Valor total</p>
                    <p className={`font-semibold ${data.type === 'receivable' ? 'text-success' : 'text-foreground'}`}>
                      {formatCurrency(data.total_value)}
                    </p>
                  </div>
                  <div>
                    <p className="text-[13px] text-muted">Parcelas</p>
                    <p className="font-semibold text-foreground">{data.installments_count} de {data.installment_total}</p>
                  </div>
                  <div>
                    <p className="text-[13px] text-muted">Período</p>
                    <p className="font-semibold text-foreground">
                      {formatDate(data.first_due_date)} — {formatDate(data.last_due_date)}
                    </p>
                  </div>
                  <div>
                    <p className="text-[13px] text-muted">Progresso</p>
                    <p className="font-semibold text-foreground">
                      {data.settled_count} pagas · {data.installments_count - data.settled_count - data.cancelled_count} abertas
                    </p>
                  </div>
                </div>
              </CardContent>
            </Card>

            <DataTable
              caption="Parcelas do parcelamento"
              columns={columns}
              rows={data.installments}
              rowKey={(a) => a.id}
              loading={query.isPending}
            />
          </>
        )}
      </PageContent>
    </Page>
  )
}
