import { useState } from 'react'
import { BarChart3, ClipboardList } from 'lucide-react'
import { ReportScreenViewer, ReportViewButton, type ScreenReportColumn } from '../components/ReportScreenViewer'
import { CategoryMatrixViewer, CategoryViewButton } from '../components/CategoryMatrixViewer'
import { MonthlySummaryTable } from '../components/MonthlySummaryTable'
import { MonthlySummaryViewer, MonthlySummaryViewButton } from '../components/MonthlySummaryViewer'
import { CostCenterFilter, useCostCenterLabel } from '../components/CostCenterFilter'
import { ReportExportButtons } from '../components/ReportExportButtons'
import { ReportGroupHeader } from '../components/ReportGroupHeader'
import { ProvisionMatrixTable } from '../components/ProvisionMatrixTable'
import { ProvisionMatrixViewer, ProvisionViewButton } from '../components/ProvisionMatrixViewer'
import { buildPayablesExportReport, buildPayablesReportHtml } from '../utils/payables-html-export'
import { isPayablesReportOverdue } from '../utils/payables-report'
import { PayablesReportViewer, PayablesViewButton } from '../components/PayablesReportViewer'
import { buildProvisionMatrixHtml } from '../utils/provision-html-export'
import { buildCategoryMatrixHtml } from '../utils/category-html-export'
import { buildMonthlySummaryHtml } from '../utils/monthly-summary-html-export'
import { useColumnTableState } from '../hooks/useColumnTableState'
import { applyColumnTableState } from '../utils/column-table'
import { applyColumnFiltersToCategoryMatrix } from '../utils/category-matrix-filter'
import { downloadReportXlsx } from '../utils/client-xlsx-export'
import {
  Badge,
  Button,
  Card,
  CardContent,
  Checkbox,
  DataTable,
  DateRangeFilter,
  EmptyState,
  Page,
  PageContent,
  PageHeader,
  Select,
  Skeleton,
  type Column,
} from '@/shared/design-system'
import { formatCurrency, formatDate, formatShortDate, toLocalIsoDate } from '@/shared/utils/format'
import { addDays, toIsoDate } from '@/shared/utils/date'
import {
  useCashFlowStatement,
  useCategoryReport,
  useCostCenterReport,
  useDailyReport,
  useMonthlySummaryReport,
  usePayablesReport,
  useProvisionReport,
  useWeeklyReport,
} from '../hooks/useReports'
import type {
  CostCenterReportRow,
  DailyCostCenterGroup,
  PayableAccount,
  WeeklyCostCenterGroup,
} from '../services/reports.service'
import { printHtmlReport } from '@/shared/utils/report-export'
import { buildReportHtml } from '../utils/report-html-export'
import { reportsService } from '../services/reports.service'

const TABS = [
  { id: 'daily', label: 'Diário' },
  { id: 'weekly', label: 'Semanal' },
  { id: 'provision', label: 'Provisão' },
  { id: 'category', label: 'Por categoria' },
  { id: 'monthly-summary', label: 'Resumo mensal' },
  { id: 'cost-center', label: 'Por centro de custo' },
  { id: 'cash-flow', label: 'Demonstrativo' },
  { id: 'payables', label: 'Contas a pagar' },
] as const

type TabId = (typeof TABS)[number]['id']

const today = toLocalIsoDate()
const defaultProvisionTo = toIsoDate(addDays(new Date(), 30))

export default function ReportsPage() {
  const [tab, setTab] = useState<TabId>('daily')

  return (
    <Page>
      <PageHeader
        title="Relatórios"
        description="Acompanhe a saúde financeira da operação."
        breadcrumb={[{ label: 'Dashboard', to: '/dashboard' }, { label: 'Relatórios' }]}
      />

      <PageContent>
        <div className="flex flex-wrap gap-2">
          {TABS.map((t) => (
            <button
              key={t.id}
              type="button"
              onClick={() => setTab(t.id)}
              className={
                t.id === tab
                  ? 'rounded-lg bg-primary px-3.5 py-2 text-sm font-medium text-primary-foreground'
                  : 'rounded-lg bg-surface-2 px-3.5 py-2 text-sm text-muted transition-colors hover:bg-surface-3 hover:text-foreground'
              }
            >
              {t.label}
            </button>
          ))}
        </div>

        {tab === 'daily' && <DailySection />}
        {tab === 'weekly' && <WeeklySection />}
        {tab === 'provision' && <ProvisionSection />}
        {tab === 'category' && <CategorySection />}
        {tab === 'monthly-summary' && <MonthlySummarySection />}
        {tab === 'cost-center' && <CostCenterSection />}
        {tab === 'cash-flow' && <CashFlowSection />}
        {tab === 'payables' && <PayablesSection />}
      </PageContent>
    </Page>
  )
}

function DailySection() {
  const [date, setDate] = useState(today)
  const [costCenterId, setCostCenterId] = useState('')
  const [viewerOpen, setViewerOpen] = useState(false)
  const { columnState, renderColumnHeader } = useColumnTableState({ key: 'value', direction: 'desc' })
  const costCenterLabel = useCostCenterLabel(costCenterId)
  const query = useDailyReport({ date, cost_center_id: costCenterId || undefined })

  type MovementRow = NonNullable<typeof query.data>['payments'][number]

  const movementAccessors = {
    description: (row: MovementRow) => row.description,
    category: (row: MovementRow) => row.category ?? '',
    value: (row: MovementRow) => row.value,
  }

  const movementTableColumns: Array<Column<MovementRow>> = [
    {
      key: 'description',
      header: renderColumnHeader('description', 'Descrição', { placeholder: 'Filtrar…' }),
      render: (row) => <span className="font-medium text-foreground">{row.description}</span>,
    },
    {
      key: 'category',
      header: renderColumnHeader('category', 'Categoria', { placeholder: 'Filtrar…' }),
      render: (row) => <span className="text-muted">{row.category ?? '—'}</span>,
    },
    {
      key: 'value',
      header: renderColumnHeader('value', 'Valor', { align: 'end', variant: 'range' }),
      className: 'text-right',
      render: (row) => <span className="font-medium text-foreground">{formatCurrency(row.value)}</span>,
    },
  ]

  const movementColumns: ScreenReportColumn<MovementRow>[] = [
    { key: 'description', header: 'Descrição', cell: (row) => row.description },
    { key: 'category', header: 'Categoria', cell: (row) => row.category ?? '—' },
    { key: 'value', header: 'Valor', align: 'right', cell: (row) => formatCurrency(row.value) },
  ]

  const dailyGroups = (query.data?.groups ?? [])
    .map((group) => {
      const payments = applyColumnTableState(group.payments, columnState, movementAccessors)
      const receipts = applyColumnTableState(group.receipts, columnState, movementAccessors)
      const total_paid = payments.reduce((sum, row) => sum + row.value, 0)
      const total_received = receipts.reduce((sum, row) => sum + row.value, 0)

      return {
        ...group,
        payments,
        receipts,
        total_paid,
        total_received,
        balance: total_received - total_paid,
      }
    })
    .filter((group) => group.payments.length > 0 || group.receipts.length > 0)

  const filteredPaid = dailyGroups.reduce((sum, group) => sum + group.total_paid, 0)
  const filteredReceived = dailyGroups.reduce((sum, group) => sum + group.total_received, 0)
  const filteredBalance = filteredReceived - filteredPaid

  const movementHeaders = ['Descrição', 'Categoria', 'Valor']
  const movementRows = (items: MovementRow[]) =>
    items.map((item) => [item.description, item.category ?? '—', formatCurrency(item.value)])
  const movementXlsxRows = (items: MovementRow[]) =>
    items.map((item) => [item.description, item.category ?? '—', item.value])
  const subtitle = `Data: ${formatDate(date)} · ${costCenterLabel}`

  const dailyScreenSections = dailyGroups.flatMap((group) => {
    const sections = []

    if (group.payments.length > 0) {
      sections.push({
        title: `${group.cost_center} · Pagamentos`,
        rows: group.payments,
        footer: { label: 'Total pago', value: formatCurrency(group.total_paid) },
      })
    }

    if (group.receipts.length > 0) {
      sections.push({
        title: `${group.cost_center} · Recebimentos`,
        rows: group.receipts,
        footer: { label: 'Total recebido', value: formatCurrency(group.total_received) },
      })
    }

    if (group.payments.length > 0 || group.receipts.length > 0) {
      sections.push({
        title: `${group.cost_center} · Saldo do centro`,
        rows: [] as MovementRow[],
        footer: { label: 'Saldo', value: formatCurrency(group.balance) },
      })
    }

    return sections
  })

  const dailyPdfSections = dailyGroups.flatMap((group) => {
    const sections = []

    if (group.payments.length > 0) {
      sections.push({
        title: `${group.cost_center} · Pagamentos`,
        headers: movementHeaders,
        rows: movementRows(group.payments),
        amountColumns: [2],
        footer: { label: 'Total pago', value: formatCurrency(group.total_paid) },
      })
    }

    if (group.receipts.length > 0) {
      sections.push({
        title: `${group.cost_center} · Recebimentos`,
        headers: movementHeaders,
        rows: movementRows(group.receipts),
        amountColumns: [2],
        footer: { label: 'Total recebido', value: formatCurrency(group.total_received) },
      })
    }

    if (group.payments.length > 0 || group.receipts.length > 0) {
      sections.push({
        title: `${group.cost_center} · Saldo do centro`,
        headers: movementHeaders,
        rows: [],
        footer: { label: 'Saldo', value: formatCurrency(group.balance) },
      })
    }

    return sections
  })

  const exportDailyXlsx = () => {
    downloadReportXlsx({
      filename: 'relatorio-diario.xlsx',
      title: 'Relatório diário',
      subtitleLines: [subtitle],
      tables: dailyGroups.flatMap((group) => {
        const tables = []

        if (group.payments.length > 0) {
          tables.push({
            banner: `${group.cost_center} · Pagamentos`,
            headers: movementHeaders,
            rows: movementXlsxRows(group.payments),
            footer: ['Total pago', '', group.total_paid],
          })
        }

        if (group.receipts.length > 0) {
          tables.push({
            banner: `${group.cost_center} · Recebimentos`,
            headers: movementHeaders,
            rows: movementXlsxRows(group.receipts),
            footer: ['Total recebido', '', group.total_received],
          })
        }

        tables.push({
          banner: `${group.cost_center} · Saldo do centro`,
          headers: movementHeaders,
          rows: [],
          footer: ['Saldo', '', group.balance],
        })

        return tables
      }),
      summary: [
        { label: 'Total pago', value: filteredPaid },
        { label: 'Total recebido', value: filteredReceived },
        { label: 'Saldo do dia', value: filteredBalance },
      ],
    })
  }

  return (
    <Card>
      <CardContent className="space-y-4">
        <div className="flex flex-wrap items-center gap-4">
          <DateRangeFilter
            variant="single"
            from={date}
            to={date}
            onChange={({ to }) => setDate(to)}
          />
          <CostCenterFilter value={costCenterId} onChange={setCostCenterId} />
        </div>

        {query.isPending ? (
          <Skeleton className="h-40" />
        ) : (
          <>
            <div className="flex flex-wrap items-start justify-between gap-4">
              <div className="grid flex-1 gap-4 sm:grid-cols-3">
                <Summary label="Total pago" value={filteredPaid} accent="text-danger" />
                <Summary label="Total recebido" value={filteredReceived} accent="text-success" />
                <Summary label="Saldo do dia" value={filteredBalance} />
              </div>
              <div className="flex flex-wrap justify-end gap-2">
                <ReportViewButton onClick={() => setViewerOpen(true)} />
                <ReportExportButtons
                  disabled={query.isPending}
                  onExportXlsx={exportDailyXlsx}
                  onExportPdf={() =>
                    printHtmlReport(
                      'Relatório diário',
                      buildReportHtml({
                        title: 'Relatório diário',
                        subtitle,
                        sections: dailyPdfSections,
                        summary: [
                          { label: 'Total pago', value: formatCurrency(filteredPaid) },
                          { label: 'Total recebido', value: formatCurrency(filteredReceived) },
                          { label: 'Saldo do dia', value: formatCurrency(filteredBalance) },
                        ],
                      }),
                    )
                  }
                />
              </div>
            </div>
            <div className="space-y-6">
              {dailyGroups.length === 0 ? (
                <EmptyState icon={BarChart3} title="Sem movimentações no dia" />
              ) : (
                dailyGroups.map((group) => (
                  <DailyCostCenterBlock key={group.cost_center} group={group} columns={movementTableColumns} />
                ))
              )}
            </div>
          </>
        )}
      </CardContent>

      <ReportScreenViewer
        open={viewerOpen}
        onClose={() => setViewerOpen(false)}
        title="Relatório diário"
        description={`Data: ${formatDate(date)} · ${costCenterLabel}`}
        columns={movementColumns}
        sections={dailyScreenSections}
        summary={[
          { label: 'Total pago', value: formatCurrency(filteredPaid) },
          { label: 'Total recebido', value: formatCurrency(filteredReceived) },
          { label: 'Saldo do dia', value: formatCurrency(filteredBalance) },
        ]}
        rowKey={(row, index) => `${row.description}-${index}`}
      />
    </Card>
  )
}

function WeeklySection() {
  const [from, setFrom] = useState('')
  const [to, setTo] = useState('')
  const [costCenterId, setCostCenterId] = useState('')
  const [viewerOpen, setViewerOpen] = useState(false)
  const { columnState, renderColumnHeader } = useColumnTableState({ key: 'total_paid', direction: 'desc' })
  const costCenterLabel = useCostCenterLabel(costCenterId)
  const query = useWeeklyReport({ from: from || undefined, to: to || undefined, cost_center_id: costCenterId || undefined })

  type WeeklyGroupRow = WeeklyCostCenterGroup

  const weeklyAccessors = {
    cost_center: (row: WeeklyGroupRow) => row.cost_center,
    total_paid: (row: WeeklyGroupRow) => row.total_paid,
    total_received: (row: WeeklyGroupRow) => row.total_received,
    net_balance: (row: WeeklyGroupRow) => row.net_balance,
  }

  const weeklyGroups = applyColumnTableState(query.data?.groups ?? [], columnState, weeklyAccessors)
  const filteredPaid = weeklyGroups.reduce((sum, group) => sum + group.total_paid, 0)
  const filteredReceived = weeklyGroups.reduce((sum, group) => sum + group.total_received, 0)
  const filteredBalance = weeklyGroups.reduce((sum, group) => sum + group.net_balance, 0)

  const weeklyColumns: ScreenReportColumn<WeeklyGroupRow>[] = [
    { key: 'cost_center', header: 'Centro de custo', cell: (row) => row.cost_center },
    { key: 'total_paid', header: 'Total pago', align: 'right', cell: (row) => formatCurrency(row.total_paid) },
    { key: 'total_received', header: 'Total recebido', align: 'right', cell: (row) => formatCurrency(row.total_received) },
    { key: 'net_balance', header: 'Saldo líquido', align: 'right', cell: (row) => formatCurrency(row.net_balance) },
  ]

  const periodLabel =
    query.data?.from && query.data?.to
      ? `${formatDate(query.data.from)} até ${formatDate(query.data.to)}`
      : from && to
        ? `${formatDate(from)} até ${formatDate(to)}`
        : 'Período não definido'

  const subtitle = `Período: ${periodLabel} · ${costCenterLabel}`

  const exportWeeklyXlsx = () => {
    downloadReportXlsx({
      filename: 'relatorio-semanal.xlsx',
      title: 'Relatório semanal',
      subtitleLines: [subtitle],
      tables: [
        {
          headers: ['Centro de custo', 'Total pago', 'Total recebido', 'Saldo líquido'],
          rows: weeklyGroups.map((group) => [
            group.cost_center,
            group.total_paid,
            group.total_received,
            group.net_balance,
          ]),
        },
      ],
      summary: [
        { label: 'Total pago', value: filteredPaid },
        { label: 'Total recebido', value: filteredReceived },
        { label: 'Saldo líquido', value: filteredBalance },
      ],
    })
  }

  return (
    <Card>
      <CardContent className="space-y-4">
        <div className="flex flex-wrap items-center gap-4">
          <DateRangeFilter
            from={from}
            to={to}
            onChange={({ from: nextFrom, to: nextTo }) => {
              setFrom(nextFrom)
              setTo(nextTo)
            }}
          />
          <CostCenterFilter value={costCenterId} onChange={setCostCenterId} />
        </div>

        {query.isPending ? (
          <Skeleton className="h-32" />
        ) : (
          <div className="flex flex-wrap items-start justify-between gap-4">
            <div className="grid flex-1 gap-4 sm:grid-cols-3">
              <Summary label="Total pago" value={filteredPaid} accent="text-danger" />
              <Summary label="Total recebido" value={filteredReceived} accent="text-success" />
              <Summary label="Saldo líquido" value={filteredBalance} />
            </div>
            <div className="flex flex-wrap justify-end gap-2">
              <ReportViewButton onClick={() => setViewerOpen(true)} />
              <ReportExportButtons
                disabled={query.isPending}
                onExportXlsx={exportWeeklyXlsx}
                onExportPdf={() =>
                  printHtmlReport(
                    'Relatório semanal',
                    buildReportHtml({
                      title: 'Relatório semanal',
                      subtitle,
                      sections: weeklyGroups.map((group) => ({
                        title: group.cost_center,
                        headers: ['Total pago', 'Total recebido', 'Saldo líquido'],
                        rows: [[formatCurrency(group.total_paid), formatCurrency(group.total_received), formatCurrency(group.net_balance)]],
                        amountColumns: [0, 1, 2],
                        footer: { label: 'Saldo do centro', value: formatCurrency(group.net_balance) },
                      })),
                      summary: [
                        { label: 'Total pago', value: formatCurrency(filteredPaid) },
                        { label: 'Total recebido', value: formatCurrency(filteredReceived) },
                        { label: 'Saldo líquido', value: formatCurrency(filteredBalance) },
                      ],
                    }),
                  )
                }
              />
            </div>
          </div>
        )}
        {!query.isPending && (
          <DataTable
            columns={[
              {
                key: 'cost_center',
                header: renderColumnHeader('cost_center', 'Centro de custo', { placeholder: 'Filtrar…' }),
                render: (row) => <span className="font-medium text-foreground">{row.cost_center}</span>,
              },
              {
                key: 'total_paid',
                header: renderColumnHeader('total_paid', 'Total pago', { align: 'end', variant: 'range' }),
                className: 'text-right',
                render: (row) => <span className="text-danger">{formatCurrency(row.total_paid)}</span>,
              },
              {
                key: 'total_received',
                header: renderColumnHeader('total_received', 'Total recebido', { align: 'end', variant: 'range' }),
                className: 'text-right',
                render: (row) => <span className="text-success">{formatCurrency(row.total_received)}</span>,
              },
              {
                key: 'net_balance',
                header: renderColumnHeader('net_balance', 'Saldo líquido', { align: 'end', variant: 'range' }),
                className: 'text-right',
                render: (row) => <span className="font-medium text-foreground">{formatCurrency(row.net_balance)}</span>,
              },
            ]}
            rows={weeklyGroups}
            rowKey={(row) => row.cost_center}
            loading={query.isPending}
            emptyState={<EmptyState icon={BarChart3} title="Sem movimentações no período" />}
          />
        )}
      </CardContent>

      <ReportScreenViewer
        open={viewerOpen}
        onClose={() => setViewerOpen(false)}
        title="Relatório semanal"
        description={subtitle}
        columns={weeklyColumns}
        sections={weeklyGroups.map((group) => ({
          title: group.cost_center,
          rows: [group],
          footer: { label: 'Saldo do centro', value: formatCurrency(group.net_balance) },
        }))}
        summary={[
          { label: 'Total pago', value: formatCurrency(filteredPaid) },
          { label: 'Total recebido', value: formatCurrency(filteredReceived) },
          { label: 'Saldo líquido', value: formatCurrency(filteredBalance) },
        ]}
        rowKey={(row) => row.cost_center}
      />
    </Card>
  )
}

function ProvisionSection() {
  const [from, setFrom] = useState(today)
  const [to, setTo] = useState(defaultProvisionTo)
  const [costCenterId, setCostCenterId] = useState('')
  const [viewerOpen, setViewerOpen] = useState(false)
  const costCenterLabel = useCostCenterLabel(costCenterId)
  const query = useProvisionReport({
    from: from || undefined,
    to: to || undefined,
    cost_center_id: costCenterId || undefined,
  })

  const data = query.data
  const exportParams = { from: from || undefined, to: to || undefined, cost_center_id: costCenterId || undefined }
  const periodFrom = data?.from ?? from
  const periodTo = data?.to ?? to
  const subtitle = `Período: ${formatDate(periodFrom)} até ${formatDate(periodTo)} · ${costCenterLabel}`

  const exportProvisionPdf = () => {
    if (!data) return

    printHtmlReport(
      'Relatório de provisão',
      buildProvisionMatrixHtml(data, 'Relatório de provisão', subtitle),
    )
  }

  return (
    <Card>
      <CardContent className="space-y-4">
        <div className="flex flex-wrap items-center gap-4">
          <DateRangeFilter
            from={from}
            to={to}
            onChange={({ from: nextFrom, to: nextTo }) => {
              setFrom(nextFrom)
              setTo(nextTo)
            }}
          />
          <CostCenterFilter value={costCenterId} onChange={setCostCenterId} />
        </div>

        {query.isPending ? (
          <Skeleton className="h-48" />
        ) : data ? (
          <>
            <Summary label="Total a pagar" value={data.total_out} accent="text-danger" />

            <div className="flex flex-wrap justify-end gap-2">
              <ProvisionViewButton onClick={() => setViewerOpen(true)} disabled={data.groups.length === 0} />
              <ReportExportButtons
                disabled={data.groups.length === 0}
                onExportXlsx={() => reportsService.provisionExport(exportParams)}
                onExportPdf={exportProvisionPdf}
              />
            </div>

            {data.groups.length === 0 ? (
              <EmptyState icon={BarChart3} title="Sem provisões no período" description="Não há contas em aberto para o filtro selecionado." />
            ) : (
              <ProvisionMatrixTable data={data} />
            )}
          </>
        ) : null}
      </CardContent>

      {data && (
        <ProvisionMatrixViewer
          open={viewerOpen}
          onClose={() => setViewerOpen(false)}
          data={data}
          costCenterLabel={costCenterLabel}
        />
      )}
    </Card>
  )
}

function CategorySection() {
  const [from, setFrom] = useState('')
  const [to, setTo] = useState('')
  const [costCenterId, setCostCenterId] = useState('')
  const [viewerOpen, setViewerOpen] = useState(false)
  const { columnState, renderColumnHeader } = useColumnTableState({ key: 'total', direction: 'desc' })
  const costCenterLabel = useCostCenterLabel(costCenterId)
  const query = useCategoryReport({
    from: from || undefined,
    to: to || undefined,
    cost_center_id: costCenterId || undefined,
  })

  type CategoryRow = { category: string; total: number }

  const categoryAccessors = {
    category: (row: CategoryRow) => row.category,
    total: (row: CategoryRow) => row.total,
  }

  const categoryTableColumns: Array<Column<CategoryRow>> = [
    {
      key: 'category',
      header: renderColumnHeader('category', 'Categoria', { placeholder: 'Filtrar…' }),
      render: (row) => <span className="text-sm text-foreground">{row.category}</span>,
    },
    {
      key: 'total',
      header: renderColumnHeader('total', 'Valor', { align: 'end', variant: 'range' }),
      className: 'text-right',
      render: (row) => <span className="text-sm font-medium text-danger">{formatCurrency(row.total)}</span>,
    },
  ]

  const periodLabel =
    query.data?.from && query.data?.to
      ? `${formatDate(query.data.from)} até ${formatDate(query.data.to)}`
      : from && to
        ? `${formatDate(from)} até ${formatDate(to)}`
        : 'Período não definido'

  const categoryGroups = (query.data?.groups ?? [])
    .map((group) => {
      const expense = applyColumnTableState(group.expense, columnState, categoryAccessors)
      return {
        ...group,
        expense,
        total_expense: expense.reduce((sum, row) => sum + row.total, 0),
      }
    })
    .filter((group) => group.expense.length > 0)

  const matrix = query.data?.matrix
  const filteredMatrix = matrix ? applyColumnFiltersToCategoryMatrix(matrix, columnState) : null
  const hasFilteredData = categoryGroups.length > 0 || (filteredMatrix?.groups.length ?? 0) > 0
  const subtitle = `Período: ${periodLabel} · ${costCenterLabel}`

  const exportCategoryPdf = () => {
    if (filteredMatrix && filteredMatrix.groups.length > 0) {
      printHtmlReport(
        'Relatório por categoria',
        buildCategoryMatrixHtml(filteredMatrix, 'Relatório por categoria', subtitle),
      )
      return
    }

    printHtmlReport(
      'Relatório por categoria',
      buildReportHtml({
        title: 'Relatório por categoria',
        subtitle,
        sections: categoryGroups.map((group) => ({
          title: group.cost_center,
          headers: ['Categoria', 'Valor'],
          rows: group.expense.map((row) => [row.category, formatCurrency(row.total)]),
          amountColumns: [1],
          footer: { label: 'Total', value: formatCurrency(group.total_expense) },
        })),
      }),
    )
  }

  const exportCategoryXlsx = () => {
    if (filteredMatrix && filteredMatrix.groups.length > 0) {
      downloadReportXlsx({
        filename: 'relatorio-por-categoria.xlsx',
        title: 'Relatório por categoria',
        subtitleLines: [subtitle],
        tables: filteredMatrix.groups.flatMap((group) => {
          const headers = ['Descrição', ...filteredMatrix.columns.map((column) => column.label), 'Total geral']
          const rows: Array<Array<string | number | null>> = []

          for (const category of group.categories) {
            rows.push([
              `${category.category} - Totais`,
              ...filteredMatrix.columns.map((column) => category.subtotal.amounts[column.key] ?? null),
              category.subtotal.total,
            ])
            for (const subcategory of category.subcategories) {
              rows.push([
                `${subcategory.subcategory} - Totais`,
                ...filteredMatrix.columns.map((column) => subcategory.subtotal.amounts[column.key] ?? null),
                subcategory.subtotal.total,
              ])
            }
          }

          return [
            {
              banner: group.cost_center,
              headers,
              rows,
              footer: [
                `${group.cost_center} - Totais`,
                ...filteredMatrix.columns.map((column) => group.subtotal.amounts[column.key] ?? null),
                group.subtotal.total,
              ],
            },
          ]
        }),
        summary: [
          { label: 'Total geral do período', value: filteredMatrix.grand_total.total },
        ],
      })
      return
    }

    downloadReportXlsx({
      filename: 'relatorio-por-categoria.xlsx',
      title: 'Relatório por categoria',
      subtitleLines: [subtitle],
      tables: categoryGroups.map((group) => ({
        banner: group.cost_center,
        headers: ['Categoria', 'Valor'],
        rows: group.expense.map((row) => [row.category, row.total]),
        footer: ['Total', group.total_expense],
      })),
    })
  }

  return (
    <Card>
      <CardContent className="space-y-4">
        <div className="flex flex-wrap items-center gap-4">
          <DateRangeFilter
            from={from}
            to={to}
            onChange={({ from: nextFrom, to: nextTo }) => {
              setFrom(nextFrom)
              setTo(nextTo)
            }}
          />
          <CostCenterFilter value={costCenterId} onChange={setCostCenterId} />
        </div>

        {query.isPending ? (
          <Skeleton className="h-40" />
        ) : (
          <>
            <div className="flex flex-wrap justify-end gap-2">
              <CategoryViewButton onClick={() => setViewerOpen(true)} disabled={!hasFilteredData} />
              <ReportExportButtons
                disabled={!hasFilteredData}
                onExportXlsx={exportCategoryXlsx}
                onExportPdf={exportCategoryPdf}
              />
            </div>
            <div className="space-y-6">
              {categoryGroups.length === 0 ? (
                <EmptyState icon={BarChart3} title="Sem dados no período" />
              ) : (
                categoryGroups.map((group) => (
                  <div key={group.cost_center}>
                    <ReportGroupHeader title={group.cost_center} subtitle={`Despesas: ${formatCurrency(group.total_expense)}`} />
                    <DataTable
                      columns={categoryTableColumns}
                      rows={group.expense}
                      rowKey={(row) => row.category}
                      emptyState={<EmptyState icon={BarChart3} title="Nenhum dado no período" />}
                    />
                  </div>
                ))
              )}
            </div>
          </>
        )}
      </CardContent>

      {filteredMatrix && query.data && (
        <CategoryMatrixViewer
          open={viewerOpen}
          onClose={() => setViewerOpen(false)}
          matrix={filteredMatrix}
          from={query.data.from}
          to={query.data.to}
          costCenterLabel={costCenterLabel}
        />
      )}
    </Card>
  )
}

function MonthlySummarySection() {
  const [from, setFrom] = useState('')
  const [to, setTo] = useState('')
  const [costCenterId, setCostCenterId] = useState('')
  const [viewerOpen, setViewerOpen] = useState(false)
  const costCenterLabel = useCostCenterLabel(costCenterId)
  const query = useMonthlySummaryReport({
    from: from || undefined,
    to: to || undefined,
    cost_center_id: costCenterId || undefined,
  })

  const data = query.data
  const exportParams = { from: from || undefined, to: to || undefined, cost_center_id: costCenterId || undefined }
  const periodFrom = data?.from ?? from
  const periodTo = data?.to ?? to
  const subtitle = `Período: ${formatDate(periodFrom)} até ${formatDate(periodTo)} · ${costCenterLabel}`
  const hasData = (data?.rows.length ?? 0) > 0

  const exportMonthlySummaryPdf = () => {
    if (!data) return

    printHtmlReport('Resumo mensal', buildMonthlySummaryHtml(data, 'Resumo mensal por centro de custo', subtitle))
  }

  return (
    <Card>
      <CardContent className="space-y-4">
        <div className="flex flex-wrap items-center gap-4">
          <DateRangeFilter
            from={from}
            to={to}
            onChange={({ from: nextFrom, to: nextTo }) => {
              setFrom(nextFrom)
              setTo(nextTo)
            }}
          />
          <CostCenterFilter value={costCenterId} onChange={setCostCenterId} />
        </div>

        {query.isPending ? (
          <Skeleton className="h-48" />
        ) : data ? (
          <>
            <div className="grid gap-4 sm:grid-cols-2">
              <Summary label="Total geral" value={data.grand_total.total} accent="text-danger" />
              <Summary label="Média mês" value={data.monthly_average} accent="text-danger" />
            </div>

            <div className="flex flex-wrap justify-end gap-2">
              <MonthlySummaryViewButton onClick={() => setViewerOpen(true)} disabled={!hasData} />
              <ReportExportButtons
                disabled={!hasData}
                onExportXlsx={() => reportsService.monthlySummaryExport(exportParams)}
                onExportPdf={exportMonthlySummaryPdf}
              />
            </div>

            {!hasData ? (
              <EmptyState icon={BarChart3} title="Sem despesas no período" description="Não há lançamentos liquidados para o filtro selecionado." />
            ) : (
              <MonthlySummaryTable data={data} />
            )}
          </>
        ) : null}
      </CardContent>

      {data && (
        <MonthlySummaryViewer
          open={viewerOpen}
          onClose={() => setViewerOpen(false)}
          data={data}
          costCenterLabel={costCenterLabel}
        />
      )}
    </Card>
  )
}

function CostCenterSection() {
  const [costCenterId, setCostCenterId] = useState('')
  const [viewerOpen, setViewerOpen] = useState(false)
  const { columnState, renderColumnHeader } = useColumnTableState({ key: 'expense', direction: 'desc' })
  const costCenterLabel = useCostCenterLabel(costCenterId)
  const query = useCostCenterReport({ cost_center_id: costCenterId || undefined })

  const accessors = {
    cost_center: (row: CostCenterReportRow) => row.cost_center,
    initial_balance: (row: CostCenterReportRow) => row.initial_balance,
    income: (row: CostCenterReportRow) => row.income,
    expense: (row: CostCenterReportRow) => row.expense,
    balance: (row: CostCenterReportRow) => row.balance,
  }

  const rows = applyColumnTableState(query.data?.rows ?? [], columnState, accessors)

  const columns: Array<Column<CostCenterReportRow>> = [
    {
      key: 'name',
      header: renderColumnHeader('cost_center', 'Centro de custo', { placeholder: 'Filtrar…' }),
      render: (r) => <span className="font-medium text-foreground">{r.cost_center}</span>,
    },
    {
      key: 'initial',
      header: renderColumnHeader('initial_balance', 'Saldo inicial', { align: 'end', variant: 'range' }),
      className: 'text-right',
      render: (r) => <span className="text-muted">{formatCurrency(r.initial_balance)}</span>,
    },
    {
      key: 'income',
      header: renderColumnHeader('income', 'Entradas', { align: 'end', variant: 'range' }),
      className: 'text-right',
      render: (r) => <span className="text-success">{formatCurrency(r.income)}</span>,
    },
    {
      key: 'expense',
      header: renderColumnHeader('expense', 'Saídas', { align: 'end', variant: 'range' }),
      className: 'text-right',
      render: (r) => <span className="text-danger">{formatCurrency(r.expense)}</span>,
    },
    {
      key: 'balance',
      header: renderColumnHeader('balance', 'Saldo', { align: 'end', variant: 'range' }),
      className: 'text-right',
      render: (r) => <span className="font-medium text-foreground">{formatCurrency(r.balance)}</span>,
    },
  ]

  const screenColumns: ScreenReportColumn<CostCenterReportRow>[] = [
    { key: 'name', header: 'Centro de custo', cell: (row) => row.cost_center },
    { key: 'initial', header: 'Saldo inicial', align: 'right', cell: (row) => formatCurrency(row.initial_balance) },
    { key: 'income', header: 'Entradas', align: 'right', cell: (row) => formatCurrency(row.income) },
    { key: 'expense', header: 'Saídas', align: 'right', cell: (row) => formatCurrency(row.expense) },
    { key: 'balance', header: 'Saldo', align: 'right', cell: (row) => formatCurrency(row.balance) },
  ]

  const costCenterHeaders = ['Centro de custo', 'Saldo inicial', 'Entradas', 'Saídas', 'Saldo']
  const grandTotals = {
    initial: rows.reduce((sum, row) => sum + row.initial_balance, 0),
    income: rows.reduce((sum, row) => sum + row.income, 0),
    expense: rows.reduce((sum, row) => sum + row.expense, 0),
    balance: rows.reduce((sum, row) => sum + row.balance, 0),
  }

  const exportCostCenterXlsx = () => {
    downloadReportXlsx({
      filename: 'relatorio-por-centro-de-custo.xlsx',
      title: 'Relatório por centro de custo',
      subtitleLines: [costCenterLabel],
      tables: [
        {
          headers: costCenterHeaders,
          rows: rows.map((row) => [
            row.cost_center,
            row.initial_balance,
            row.income,
            row.expense,
            row.balance,
          ]),
        },
      ],
      summary: [
        { label: 'Saldo inicial total', value: grandTotals.initial },
        { label: 'Entradas totais', value: grandTotals.income },
        { label: 'Saídas totais', value: grandTotals.expense },
        { label: 'Saldo total', value: grandTotals.balance },
      ],
    })
  }

  return (
    <Card>
      <CardContent className="space-y-4">
        <div className="flex flex-wrap items-center justify-between gap-4">
          <div className="flex flex-wrap items-center gap-4">
            <CostCenterFilter value={costCenterId} onChange={setCostCenterId} />
          </div>
          {!query.isPending && (
            <div className="flex flex-wrap justify-end gap-2">
              <ReportViewButton onClick={() => setViewerOpen(true)} />
              <ReportExportButtons
                disabled={query.isPending}
                onExportXlsx={exportCostCenterXlsx}
                onExportPdf={() =>
                  printHtmlReport(
                    'Relatório por centro de custo',
                    buildReportHtml({
                      title: 'Relatório por centro de custo',
                      subtitle: costCenterLabel,
                      sections: [
                        {
                          headers: costCenterHeaders,
                          rows: rows.map((row) => [
                            row.cost_center,
                            formatCurrency(row.initial_balance),
                            formatCurrency(row.income),
                            formatCurrency(row.expense),
                            formatCurrency(row.balance),
                          ]),
                          amountColumns: [1, 2, 3, 4],
                        },
                      ],
                      summary: [
                        { label: 'Saldo inicial total', value: formatCurrency(grandTotals.initial) },
                        { label: 'Entradas totais', value: formatCurrency(grandTotals.income) },
                        { label: 'Saídas totais', value: formatCurrency(grandTotals.expense) },
                        { label: 'Saldo total', value: formatCurrency(grandTotals.balance) },
                      ],
                    }),
                  )
                }
              />
            </div>
          )}
        </div>
        <DataTable columns={columns} rows={rows} rowKey={(r) => r.cost_center_id} loading={query.isPending} emptyState={<EmptyState icon={BarChart3} title="Sem centros de custo" />} />
        {!query.isPending && rows.length > 0 && (
          <div className="grid gap-4 sm:grid-cols-4">
            <Summary label="Saldo inicial total" value={grandTotals.initial} />
            <Summary label="Entradas totais" value={grandTotals.income} accent="text-success" />
            <Summary label="Saídas totais" value={grandTotals.expense} accent="text-danger" />
            <Summary label="Saldo total" value={grandTotals.balance} />
          </div>
        )}
      </CardContent>

      <ReportScreenViewer
        open={viewerOpen}
        onClose={() => setViewerOpen(false)}
        title="Relatório por centro de custo"
        description={costCenterLabel}
        columns={screenColumns}
        sections={rows.map((row) => ({
          title: row.cost_center,
          rows: [row],
          footer: { label: 'Saldo do centro', value: formatCurrency(row.balance) },
        }))}
        summary={[
          { label: 'Saldo inicial total', value: formatCurrency(grandTotals.initial) },
          { label: 'Entradas totais', value: formatCurrency(grandTotals.income) },
          { label: 'Saídas totais', value: formatCurrency(grandTotals.expense) },
          { label: 'Saldo total', value: formatCurrency(grandTotals.balance) },
        ]}
        rowKey={(row) => row.cost_center_id}
      />
    </Card>
  )
}

function CashFlowSection() {
  const [from, setFrom] = useState('')
  const [to, setTo] = useState('')
  const [days, setDays] = useState(30)
  const [costCenterId, setCostCenterId] = useState('')
  const [viewerOpen, setViewerOpen] = useState(false)
  const { columnState, renderColumnHeader } = useColumnTableState({ key: 'realized_net', direction: 'desc' })
  const costCenterLabel = useCostCenterLabel(costCenterId)

  const query = useCashFlowStatement({
    from: from || undefined,
    to: to || undefined,
    days,
    cost_center_id: costCenterId || undefined,
  })

  type CashFlowGroupRow = NonNullable<typeof query.data>['groups'][number]

  const accessors = {
    cost_center: (row: CashFlowGroupRow) => row.cost_center,
    realized_net: (row: CashFlowGroupRow) => row.realized_net,
    projected_net: (row: CashFlowGroupRow) => row.projected_net,
    expected_final_balance: (row: CashFlowGroupRow) => row.expected_final_balance,
  }

  const cashFlowGroups = applyColumnTableState(query.data?.groups ?? [], columnState, accessors)
  const filteredComparative = {
    realized_net: cashFlowGroups.reduce((sum, group) => sum + group.realized_net, 0),
    projected_net: cashFlowGroups.reduce((sum, group) => sum + group.projected_net, 0),
    expected_final_balance: cashFlowGroups.reduce((sum, group) => sum + group.expected_final_balance, 0),
  }

  const cashFlowGroupColumns: Array<Column<CashFlowGroupRow>> = [
    {
      key: 'cost_center',
      header: renderColumnHeader('cost_center', 'Centro de custo', { placeholder: 'Filtrar…' }),
      render: (row) => <span className="font-medium text-foreground">{row.cost_center}</span>,
    },
    {
      key: 'realized_net',
      header: renderColumnHeader('realized_net', 'Resultado realizado', { align: 'end', variant: 'range' }),
      className: 'text-right',
      render: (row) => <span className="text-foreground">{formatCurrency(row.realized_net)}</span>,
    },
    {
      key: 'projected_net',
      header: renderColumnHeader('projected_net', 'Resultado projetado', { align: 'end', variant: 'range' }),
      className: 'text-right',
      render: (row) => <span className="text-foreground">{formatCurrency(row.projected_net)}</span>,
    },
    {
      key: 'expected_final_balance',
      header: renderColumnHeader('expected_final_balance', 'Saldo final esperado', { align: 'end', variant: 'range' }),
      className: 'text-right',
      render: (row) => <span className="font-medium text-foreground">{formatCurrency(row.expected_final_balance)}</span>,
    },
  ]

  const cashFlowGroupScreenColumns: ScreenReportColumn<CashFlowGroupRow>[] = [
    { key: 'cost_center', header: 'Centro de custo', cell: (row) => row.cost_center },
    { key: 'realized_net', header: 'Resultado realizado', align: 'right', cell: (row) => formatCurrency(row.realized_net) },
    { key: 'projected_net', header: 'Resultado projetado', align: 'right', cell: (row) => formatCurrency(row.projected_net) },
    { key: 'expected_final_balance', header: 'Saldo final esperado', align: 'right', cell: (row) => formatCurrency(row.expected_final_balance) },
  ]

  const subtitle = query.data
    ? `Período: ${formatDate(query.data.realized.from)} até ${formatDate(query.data.realized.to)} · Projeção ${days} dias · ${costCenterLabel}`
    : costCenterLabel

  const exportCashFlowXlsx = () => {
    downloadReportXlsx({
      filename: 'demonstrativo-fluxo-caixa.xlsx',
      title: 'Demonstrativo de fluxo de caixa',
      subtitleLines: [subtitle],
      tables: [
        {
          headers: ['Centro de custo', 'Resultado realizado', 'Resultado projetado', 'Saldo final esperado'],
          rows: cashFlowGroups.map((group) => [
            group.cost_center,
            group.realized_net,
            group.projected_net,
            group.expected_final_balance,
          ]),
        },
      ],
      summary: [
        { label: 'Resultado realizado', value: filteredComparative.realized_net },
        { label: 'Resultado projetado', value: filteredComparative.projected_net },
        { label: 'Saldo final esperado', value: filteredComparative.expected_final_balance },
      ],
    })
  }

  return (
    <Card>
      <CardContent className="space-y-4">
        <div className="flex flex-wrap items-center gap-4">
          <DateRangeFilter
            from={from}
            to={to}
            onChange={({ from: nextFrom, to: nextTo }) => {
              setFrom(nextFrom)
              setTo(nextTo)
            }}
          />
          <Select
            aria-label="Projeção"
            className="w-40"
            value={String(days)}
            onChange={(e) => setDays(Number(e.target.value))}
            options={[
              { value: '30', label: '30 dias' },
              { value: '60', label: '60 dias' },
              { value: '90', label: '90 dias' },
            ]}
          />
          <CostCenterFilter value={costCenterId} onChange={setCostCenterId} />
          {query.data && (
            <>
              <ReportViewButton onClick={() => setViewerOpen(true)} />
              <ReportExportButtons
                onExportXlsx={exportCashFlowXlsx}
                onExportPdf={() =>
                  printHtmlReport(
                    'Demonstrativo de fluxo de caixa',
                    buildReportHtml({
                      title: 'Demonstrativo de fluxo de caixa',
                      subtitle,
                      sections: [
                        {
                          headers: ['Centro de custo', 'Resultado realizado', 'Resultado projetado', 'Saldo final esperado'],
                          rows: cashFlowGroups.map((group) => [
                            group.cost_center,
                            formatCurrency(group.realized_net),
                            formatCurrency(group.projected_net),
                            formatCurrency(group.expected_final_balance),
                          ]),
                          amountColumns: [1, 2, 3],
                        },
                      ],
                      summary: [
                        { label: 'Resultado realizado', value: formatCurrency(filteredComparative.realized_net) },
                        { label: 'Resultado projetado', value: formatCurrency(filteredComparative.projected_net) },
                        { label: 'Saldo final esperado', value: formatCurrency(filteredComparative.expected_final_balance) },
                      ],
                    }),
                  )
                }
              />
            </>
          )}
        </div>

        {query.isPending ? (
          <Skeleton className="h-48" />
        ) : (
          query.data && (
            <>
              <div className="grid gap-4 sm:grid-cols-3">
                <Summary label="Resultado realizado" value={filteredComparative.realized_net} />
                <Summary label="Resultado projetado" value={filteredComparative.projected_net} />
                <Summary label="Saldo final esperado" value={filteredComparative.expected_final_balance} />
              </div>
              <DataTable
                columns={cashFlowGroupColumns}
                rows={cashFlowGroups}
                rowKey={(row) => row.cost_center}
                emptyState={<EmptyState icon={BarChart3} title="Sem centros de custo" />}
              />
            </>
          )
        )}
      </CardContent>

      <ReportScreenViewer
        open={viewerOpen}
        onClose={() => setViewerOpen(false)}
        title="Demonstrativo de fluxo de caixa"
        description={subtitle}
        columns={cashFlowGroupScreenColumns}
        sections={[
          {
            rows: cashFlowGroups,
          },
        ]}
        summary={[
          { label: 'Resultado realizado', value: formatCurrency(filteredComparative.realized_net) },
          { label: 'Resultado projetado', value: formatCurrency(filteredComparative.projected_net) },
          { label: 'Saldo final esperado', value: formatCurrency(filteredComparative.expected_final_balance) },
        ]}
        rowKey={(row) => row.cost_center}
      />
    </Card>
  )
}

function PayablesSection() {
  const [from, setFrom] = useState('')
  const [to, setTo] = useState('')
  const [costCenterId, setCostCenterId] = useState('')
  const [selected, setSelected] = useState<Set<string>>(new Set())
  const [viewerOpen, setViewerOpen] = useState(false)
  const { columnState, renderColumnHeader } = useColumnTableState({ key: 'due_date', direction: 'asc' })
  const costCenterLabel = useCostCenterLabel(costCenterId)

  const query = usePayablesReport({
    from: from || undefined,
    to: to || undefined,
    cost_center_id: costCenterId || undefined,
  })

  const payableAccessors = {
    due_date: (account: PayableAccount) => account.due_date,
    description: (account: PayableAccount) => account.description,
    installment: (account: PayableAccount) => account.installment ?? '',
    remaining_amount: (account: PayableAccount) => account.remaining_amount,
  }

  const data = query.data
  const accounts = applyColumnTableState(data?.accounts ?? [], columnState, payableAccessors)
  const groups = (data?.groups ?? [])
    .map((group) => ({
      ...group,
      accounts: applyColumnTableState(group.accounts, columnState, payableAccessors),
    }))
    .filter((group) => group.accounts.length > 0)

  const filteredPayablesData = data
    ? {
        ...data,
        accounts,
        groups,
      }
    : null

  const toggle = (id: string) => {
    setSelected((prev) => {
      const next = new Set(prev)
      if (next.has(id)) {
        next.delete(id)
      } else {
        next.add(id)
      }
      return next
    })
  }

  const allSelected = accounts.length > 0 && accounts.every((account) => selected.has(account.id))

  const toggleAll = () => {
    setSelected(allSelected ? new Set() : new Set(accounts.map((account) => account.id)))
  }

  const selectedAccounts = accounts.filter((account) => selected.has(account.id))
  const remainingAccounts = accounts.filter((account) => !selected.has(account.id))
  const overdueRemainingAccounts = accounts.filter((account) => isPayablesReportOverdue(account, selected))
  const totalOverdue = overdueRemainingAccounts.reduce((sum, account) => sum + account.remaining_amount, 0)
  const totalPaidToday = selectedAccounts.reduce((sum, account) => sum + account.remaining_amount, 0)
  const totalRemaining = remainingAccounts.reduce((sum, account) => sum + account.remaining_amount, 0)
  const totalAnalyzed = accounts.reduce((sum, account) => sum + account.remaining_amount, 0)

  const exportSubtitle = data
    ? `Referência: ${formatShortDate(data.reference_date)} · Período: ${formatShortDate(data.from)} até ${formatShortDate(data.to)} · ${costCenterLabel}`
    : costCenterLabel

  const exportViewData = filteredPayablesData ? buildPayablesExportReport(filteredPayablesData, selected) : null

  const exportPayablesPdf = () => {
    if (!exportViewData) return
    printHtmlReport('Relatório de contas a pagar', buildPayablesReportHtml(exportViewData, 'Relatório de contas a pagar', exportSubtitle))
  }

  const exportPayablesXlsx = () => {
    if (!exportViewData) return

    const referenceDate = formatShortDate(exportViewData.reference_date)
    const tables = exportViewData.groups.flatMap((group) => {
      const groupTables = []

      groupTables.push({
        banner: group.cost_center,
        headers: ['Data', 'Descrição', 'Valor'],
        rows: [] as Array<Array<string | number | null>>,
      })

      if (group.overdue.accounts.length > 0) {
        groupTables.push({
          banner: 'EM ATRASO',
          headers: ['Data', 'Descrição', 'Valor'],
          rows: group.overdue.accounts.map((account) => [
            formatShortDate(account.due_date),
            account.description,
            account.remaining_amount,
          ]),
          footer: ['TOTAL EM ATRASO', '', group.overdue.total],
        })
      }

      if (group.due_today.accounts.length > 0) {
        groupTables.push({
          banner: `PAGOS EM ${referenceDate}`,
          headers: ['Data', 'Descrição', 'Valor'],
          rows: group.due_today.accounts.map((account) => [
            formatShortDate(account.due_date),
            account.description,
            account.remaining_amount,
          ]),
          footer: ['TOTAL PAGO', '', group.due_today.total],
        })
      }

      return groupTables
    })

    downloadReportXlsx({
      filename: 'contas-a-pagar.xlsx',
      title: 'Relatório de contas a pagar',
      subtitleLines: [exportSubtitle],
      tables: [
        ...tables,
        {
          banner: `Resumo geral em ${referenceDate}`,
          headers: ['Centro de custo', 'Pagos', 'Em atraso'],
          rows: exportViewData.summary.paid_today.rows.map((row, index) => [
            row.cost_center,
            row.amount,
            exportViewData.summary.overdue.rows[index]?.amount ?? 0,
          ]),
          footer: ['TOTAL', exportViewData.summary.paid_today.total, exportViewData.summary.overdue.total],
        },
      ],
    })
  }

  const columnsFor = (group: (typeof groups)[number], index: number): Array<Column<PayableAccount>> => {
    const allInGroupSelected = group.accounts.length > 0 && group.accounts.every((account) => selected.has(account.id))

    const toggleGroup = () => {
      setSelected((prev) => {
        const next = new Set(prev)
        if (allInGroupSelected) {
          group.accounts.forEach((account) => next.delete(account.id))
        } else {
          group.accounts.forEach((account) => next.add(account.id))
        }
        return next
      })
    }

    return [
      {
        key: 'select',
        header: (
          <Checkbox
            id={`payables-select-all-${index}`}
            checked={allInGroupSelected}
            onChange={toggleGroup}
            aria-label={`Selecionar pagamentos de ${group.cost_center}`}
          />
        ),
        render: (account) => (
          <Checkbox
            id={`payables-${account.id}`}
            checked={selected.has(account.id)}
            onChange={() => toggle(account.id)}
            aria-label={`Selecionar ${account.description}`}
          />
        ),
      },
      {
        key: 'due_date',
        header: renderColumnHeader('due_date', 'Data', { placeholder: 'Filtrar…' }),
        className: 'w-28',
        render: (account) => (
          <span className={`tabular-nums ${isPayablesReportOverdue(account, selected) ? 'font-medium text-danger' : selected.has(account.id) ? 'font-medium text-success' : 'text-muted'}`}>
            {formatShortDate(account.due_date)}
          </span>
        ),
      },
      {
        key: 'description',
        header: renderColumnHeader('description', 'Descrição', { placeholder: 'Filtrar…' }),
        render: (account) => (
          <span className="font-medium text-foreground">{account.description}</span>
        ),
      },
      {
        key: 'installment',
        header: renderColumnHeader('installment', 'Parcela', { placeholder: 'Filtrar…' }),
        className: 'w-24',
        render: (account) =>
          account.installment ? <Badge variant="neutral">{account.installment}</Badge> : <span className="text-muted">—</span>,
      },
      {
        key: 'value',
        header: renderColumnHeader('remaining_amount', 'Valor', { align: 'end', variant: 'range' }),
        className: 'w-32 text-right',
        render: (account) => (
          <span className="block text-right font-medium tabular-nums text-foreground">{formatCurrency(account.remaining_amount)}</span>
        ),
      },
    ]
  }

  return (
    <Card>
      <CardContent className="space-y-4">
        <div className="flex flex-wrap items-center gap-4">
          <DateRangeFilter
            from={from}
            to={to}
            onChange={({ from: nextFrom, to: nextTo }) => {
              setFrom(nextFrom)
              setTo(nextTo)
            }}
          />
          <CostCenterFilter value={costCenterId} onChange={setCostCenterId} />
        </div>

        {query.isPending ? (
          <Skeleton className="h-48" />
        ) : data ? (
          <>
            <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
              <Summary label="Total em atraso" value={totalOverdue} accent="text-danger" />
              <Summary label="Total pago hoje" value={totalPaidToday} accent="text-success" />
              <Summary label="Permanecerão em aberto" value={totalRemaining} />
              <Summary label="Valor geral analisado" value={totalAnalyzed} />
            </div>

            {accounts.length === 0 ? (
              <EmptyState icon={ClipboardList} title="Sem contas em aberto no período" description="Não há contas a pagar para o filtro selecionado." />
            ) : (
              <div className="space-y-6">
                <div className="flex items-center justify-between">
                  <Button variant="ghost" size="sm" onClick={toggleAll}>
                    {allSelected ? 'Limpar seleção' : 'Selecionar todas'}
                  </Button>
                  <span className="text-[13px] text-muted">
                    {selected.size} conta(s) selecionada(s) para pagamento hoje
                  </span>
                </div>

                {groups.map((group, index) => (
                  <div key={group.cost_center}>
                    <ReportGroupHeader
                      title={group.cost_center}
                      subtitle={`Em atraso: ${formatCurrency(
                        group.accounts
                          .filter((account) => isPayablesReportOverdue(account, selected))
                          .reduce((sum, account) => sum + account.remaining_amount, 0),
                      )} · Selecionado p/ hoje: ${formatCurrency(
                        group.accounts
                          .filter((account) => selected.has(account.id))
                          .reduce((sum, account) => sum + account.remaining_amount, 0),
                      )}`}
                    />
                    <div className="overflow-x-auto">
                      <DataTable columns={columnsFor(group, index)} rows={group.accounts} rowKey={(account) => account.id} />
                    </div>
                  </div>
                ))}
              </div>
            )}

            <div className="flex flex-wrap justify-end gap-2">
              <PayablesViewButton onClick={() => setViewerOpen(true)} disabled={accounts.length === 0} />
              <ReportExportButtons
                disabled={accounts.length === 0}
                onExportXlsx={exportPayablesXlsx}
                onExportPdf={exportPayablesPdf}
              />
            </div>
          </>
        ) : null}
      </CardContent>

      {exportViewData && (
        <PayablesReportViewer
          open={viewerOpen}
          onClose={() => setViewerOpen(false)}
          data={exportViewData}
          costCenterLabel={costCenterLabel}
        />
      )}
    </Card>
  )
}

function Summary({ label, value, accent = 'text-foreground' }: { label: string; value: number; accent?: string }) {
  return (
    <div className="rounded-xl bg-surface-2/60 p-4">
      <p className="text-[13px] text-muted">{label}</p>
      <p className={`text-lg font-semibold ${accent}`}>{formatCurrency(value)}</p>
    </div>
  )
}

function DailyCostCenterBlock({
  group,
  columns,
}: {
  group: DailyCostCenterGroup
  columns: Array<Column<DailyCostCenterGroup['payments'][number]>>
}) {
  return (
    <div>
      <ReportGroupHeader
        title={group.cost_center}
        total={group.balance}
        subtitle={`Pago: ${formatCurrency(group.total_paid)} · Recebido: ${formatCurrency(group.total_received)}`}
      />
      <div className="space-y-4">
        {group.payments.length > 0 && (
          <div>
            <h4 className="mb-2 text-[13px] font-semibold text-danger">Pagamentos</h4>
            <DataTable columns={columns} rows={group.payments} rowKey={(row) => `${row.description}-${row.category}-${row.value}`} />
            <p className="mt-1 text-right text-[13px] font-medium text-foreground">Total pago: {formatCurrency(group.total_paid)}</p>
          </div>
        )}
        {group.receipts.length > 0 && (
          <div>
            <h4 className="mb-2 text-[13px] font-semibold text-success">Recebimentos</h4>
            <DataTable columns={columns} rows={group.receipts} rowKey={(row) => `${row.description}-${row.category}-${row.value}`} />
            <p className="mt-1 text-right text-[13px] font-medium text-foreground">Total recebido: {formatCurrency(group.total_received)}</p>
          </div>
        )}
      </div>
    </div>
  )
}
