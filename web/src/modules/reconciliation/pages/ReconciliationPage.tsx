import { useRef, useState } from 'react'
import { useSearchParams } from 'react-router'
import { FileUp, Landmark, Link2, RotateCcw, Upload, Wand2, XCircle } from 'lucide-react'
import {
  Badge,
  Button,
  Card,
  CardContent,
  DataTable,
  DateRangeFilter,
  EmptyState,
  Page,
  PageContent,
  PageHeader,
  Pagination,
  Select,
  type Column,
} from '@/shared/design-system'
import { Can } from '@/app/guards/PermissionGuard'
import { Permission } from '@/shared/constants/permissions'
import { usePermissions } from '@/shared/hooks/usePermissions'
import { formatCurrency, formatDate } from '@/shared/utils/format'
import { useCostCenterOptions } from '@/modules/cost-centers/hooks/useCostCenters'
import type { BankTransaction } from '@/shared/types/models'
import {
  useAutoReconcile,
  useIgnoreTransaction,
  useImportOfx,
  useReconciliationQuery,
  useUndoReconciliation,
} from '../hooks/useReconciliation'
import { MatchDialog } from '../components/MatchDialog'

const PER_PAGE = 10

const STATUS_BADGES: Record<BankTransaction['status'], { variant: 'neutral' | 'primary' | 'success' | 'warning' | 'danger'; label: string }> = {
  pending: { variant: 'warning', label: 'Pendente' },
  matched: { variant: 'success', label: 'Conciliada' },
  ignored: { variant: 'neutral', label: 'Ignorada' },
}

export default function ReconciliationPage() {
  const [searchParams, setSearchParams] = useSearchParams()
  const page = Number(searchParams.get('page') ?? 1)

  const { can } = usePermissions()

  const costCenters = useCostCenterOptions()
  const importOfx = useImportOfx()
  const autoReconcile = useAutoReconcile()
  const ignore = useIgnoreTransaction()
  const undo = useUndoReconciliation()

  const [costCenterId, setCostCenterId] = useState('')
  const [from, setFrom] = useState('')
  const [to, setTo] = useState('')
  const [fileName, setFileName] = useState('')
  const [matching, setMatching] = useState<BankTransaction | null>(null)
  const fileRef = useRef<HTMLInputElement>(null)

  const query = useReconciliationQuery({ page, per_page: PER_PAGE, status: 'pending' })

  const handleFile = async (file: File | null) => {
    if (!file) return
    setFileName(file.name)
    const content = await file.text()
    const cc = costCenterId || costCenters.data?.[0]?.value

    if (!cc) return
    await importOfx.mutateAsync({ costCenterId: cc, content })
    if (fileRef.current) fileRef.current.value = ''
  }

  const columns: Array<Column<BankTransaction>> = [
    {
      key: 'date',
      header: 'Data',
      render: (t) => <span className="text-muted">{formatDate(t.date)}</span>,
    },
    {
      key: 'value',
      header: 'Valor',
      render: (t) => (
        <span className={t.type === 'credit' ? 'font-medium text-success' : 'font-medium text-foreground'}>
          {formatCurrency(t.value)}
        </span>
      ),
    },
    {
      key: 'status',
      header: 'Status',
      render: (t) => {
        const s = STATUS_BADGES[t.status]
        return <Badge variant={s.variant}>{s.label}</Badge>
      },
    },
    {
      key: 'actions',
      header: <span className="sr-only">Ações</span>,
      className: 'w-32 text-right',
      render: (t: BankTransaction) => (
        <div className="flex items-center justify-end gap-1">
          {t.status === 'pending' && can(Permission.RECONCILIATION_EXECUTE) && (
            <Button variant="ghost" size="sm" onClick={() => setMatching(t)} aria-label="Conciliar" className="text-success hover:bg-success-soft hover:text-success">
              <Link2 className="size-4" />
            </Button>
          )}
          {t.status === 'pending' && can(Permission.RECONCILIATION_EXECUTE) && (
            <Button variant="ghost" size="sm" onClick={() => ignore.mutate(t.id)} aria-label="Ignorar" className="text-warning hover:bg-warning-soft hover:text-warning">
              <XCircle className="size-4" />
            </Button>
          )}
          {t.status === 'matched' && can(Permission.RECONCILIATION_UNDO) && (
            <Button variant="ghost" size="sm" onClick={() => undo.mutate(t.id)} aria-label="Desfazer" className="text-danger hover:bg-danger-soft hover:text-danger">
              <RotateCcw className="size-4" />
            </Button>
          )}
        </div>
      ),
    },
  ]

  return (
    <Page>
      <PageHeader
        title="Conciliação bancária"
        description="Importe extratos OFX e concilie com os lançamentos."
        breadcrumb={[{ label: 'Dashboard', to: '/dashboard' }, { label: 'Conciliação' }]}
      />

      <PageContent>
        <Card>
          <CardContent className="flex flex-wrap items-end gap-3">
            <div className="min-w-56 flex-1">
              <label className="mb-1.5 block text-[13px] font-medium text-foreground">Centro de custo</label>
              <Select
                aria-label="Centro de custo"
                value={costCenterId}
                onChange={(e) => setCostCenterId(e.target.value)}
                options={costCenters.data ?? []}
                placeholder="Selecione"
              />
            </div>
            <div className="min-w-56 flex-1">
              <label className="mb-1.5 block text-[13px] font-medium text-foreground">Arquivo OFX</label>
              <input
                ref={fileRef}
                type="file"
                accept=".ofx,.xml"
                onChange={(e) => handleFile(e.target.files?.[0] ?? null)}
                className="hidden"
                id="ofx-file"
              />
              <label
                htmlFor="ofx-file"
                className="flex h-10 cursor-pointer items-center gap-2 rounded-lg bg-surface-2 px-3.5 text-sm text-muted transition-colors hover:bg-surface-3"
              >
                <Upload className="size-4" />
                {fileName || 'Selecionar arquivo'}
              </label>
            </div>
            <div>
              <label className="mb-1.5 block text-[13px] font-medium text-foreground">Período (vencimento)</label>
              <DateRangeFilter
                from={from}
                to={to}
                onChange={({ from: nextFrom, to: nextTo }) => {
                  setFrom(nextFrom)
                  setTo(nextTo)
                }}
              />
            </div>
            <Can permission={Permission.RECONCILIATION_EXECUTE}>
              <Button onClick={() => autoReconcile.mutate({ from: from || undefined, to: to || undefined })} loading={autoReconcile.isPending} variant="secondary">
                <Wand2 className="size-4" />
                Conciliação automática
              </Button>
            </Can>
          </CardContent>
        </Card>
        <DataTable
          caption="Transações do extrato"
          columns={columns}
          rows={query.data?.data ?? []}
          rowKey={(t) => t.id}
          loading={query.isPending}
          emptyState={
            <EmptyState
              icon={FileUp}
              title="Nenhuma transação"
              description="Importe um extrato OFX para começar a conciliação."
              action={
                <Button variant="secondary" onClick={() => fileRef.current?.click()}>
                  <Landmark className="size-4" />
                  Importar OFX
                </Button>
              }
            />
          }
        />

        {query.data && <Pagination meta={query.data.meta} onPageChange={(next) => setSearchParams((p) => { next > 1 ? p.set('page', String(next)) : p.delete('page'); return p })} />}
      </PageContent>

      <MatchDialog transaction={matching} from={from} to={to} open={matching !== null} onClose={() => setMatching(null)} />
    </Page>
  )
}
