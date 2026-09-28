import { useEffect, useState } from 'react'
import { useSearchParams } from 'react-router'
import {
  Banknote,
  CheckCircle2,
  ChevronDown,
  CreditCard,
  Download,
  FileUp,
  Landmark,
  LayoutList,
  Plus,
  Printer,
  Rows3,
  SlidersHorizontal,
  TriangleAlert,
} from 'lucide-react'
import {
  Badge,
  Button,
  ButtonLink,
  Card,
  CardContent,
  ConfirmDialog,
  DataTable,
  DateRangeFilter,
  EmptyState,
  Page,
  PageContent,
  PageHeader,
  Pagination,
  SearchInput,
  SegmentedControl,
  Select,
  type Column,
} from '@/shared/design-system'
import { Can } from '@/app/guards/PermissionGuard'
import { Permission } from '@/shared/constants/permissions'
import { useDebounce } from '@/shared/hooks/useDebounce'
import { useBankAccountOptions } from '@/modules/bank-accounts/hooks/useBankAccounts'
import { useCostCenterOptions } from '@/modules/cost-centers/hooks/useCostCenters'
import { useCreditCardOptions } from '@/modules/credit-cards/hooks/useCreditCards'
import { cn } from '@/shared/utils/cn'
import { formatCurrency, formatDate, toLocalIsoDate } from '@/shared/utils/format'
import { openReportWindow, writeHtmlReport } from '@/shared/utils/report-export'
import { isApiError } from '@/shared/api/errors'
import { toast } from '@/shared/stores/toast.store'
import type { Account } from '@/shared/types/models'
import type { AccountListParams } from '@/shared/constants/query-keys'
import {
  useAccountsQuery,
  useCancelAccount,
  useDeleteAccount,
  useSettleAccount,
} from '../hooks/useAccounts'
import { accountsService } from '../services/accounts.service'
import { buildAccountsReportHtml } from '../utils/accounts-html-export'
import { SettleDialog } from '../components/SettleDialog'
import { ImportAccountsDialog } from '../components/ImportAccountsDialog'
import { AccountActions } from '../components/AccountActions'
import { AccountDetailPanel } from '../components/AccountDetailPanel'

const DEFAULT_PER_PAGE = 200
const PER_PAGE_OPTIONS = [10, 25, 50, 100, 200]

const STATUS_LABELS: Record<Account['status'], { variant: 'neutral' | 'primary' | 'success' | 'warning' | 'danger'; label: string }> = {
  open: { variant: 'primary', label: 'Aberto' },
  partial: { variant: 'warning', label: 'Parcial' },
  settled: { variant: 'success', label: 'Liquidado' },
  cancelled: { variant: 'neutral', label: 'Cancelado' },
}

const TYPE_OPTIONS = [
  { value: '', label: 'Todos' },
  { value: 'payable', label: 'A pagar' },
  { value: 'receivable', label: 'A receber' },
]

const STATUS_OPTIONS = [
  { value: '', label: 'Todos' },
  { value: 'open', label: 'Aberto' },
  { value: 'partial', label: 'Parcial' },
  { value: 'settled', label: 'Liquidado' },
  { value: 'cancelled', label: 'Cancelado' },
]

function accountCategoryLabel(account: Account): string {
  if (account.allocation_mode === 'split' && account.allocations?.length) {
    return account.allocations
      .map((line) => line.category?.name)
      .filter(Boolean)
      .filter((name, index, names) => names.indexOf(name) === index)
      .join(' / ')
  }

  return account.category?.name ?? ''
}

function isOverdue(account: Account, today: string): boolean {
  return (
    (account.status === 'open' || account.status === 'partial') &&
    account.due_date !== null &&
    account.due_date < today
  )
}

function rangeLine(label: string, from: string, to: string): string | null {
  if (from && to) return `${label}: ${formatDate(from)} até ${formatDate(to)}`
  if (from) return `${label}: a partir de ${formatDate(from)}`
  if (to) return `${label}: até ${formatDate(to)}`

  return null
}

export default function AccountsListPage() {
  const [searchParams, setSearchParams] = useSearchParams()
  const [search, setSearch] = useState(searchParams.get('search') ?? '')
  const [dueFrom, setDueFrom] = useState(searchParams.get('due_from') ?? '')
  const [dueTo, setDueTo] = useState(searchParams.get('due_to') ?? '')
  const [paidFrom, setPaidFrom] = useState(searchParams.get('paid_from') ?? '')
  const [paidTo, setPaidTo] = useState(searchParams.get('paid_to') ?? '')
  const debouncedSearch = useDebounce(search)
  const page = Number(searchParams.get('page') ?? 1)
  const perPage = Number(searchParams.get('per_page') ?? DEFAULT_PER_PAGE) || DEFAULT_PER_PAGE
  const type = searchParams.get('type') ?? ''
  const status = searchParams.get('status') ?? ''
  const overdue = searchParams.get('overdue') === '1'
  const bankAccountId = searchParams.get('bank_account_id') ?? ''
  const creditCardId = searchParams.get('credit_card_id') ?? ''
  const costCenterId = searchParams.get('cost_center_id') ?? ''
  const detailed = searchParams.get('view') === 'detailed'
  const today = toLocalIsoDate()

  useEffect(() => {
    setDueFrom(searchParams.get('due_from') ?? '')
    setDueTo(searchParams.get('due_to') ?? '')
    setPaidFrom(searchParams.get('paid_from') ?? '')
    setPaidTo(searchParams.get('paid_to') ?? '')
  }, [searchParams])

  const bankAccounts = useBankAccountOptions()
  const creditCards = useCreditCardOptions()
  const costCenters = useCostCenterOptions()

  const [toDelete, setToDelete] = useState<Account | null>(null)
  const [toCancel, setToCancel] = useState<Account | null>(null)
  const [toSettle, setToSettle] = useState<Account | null>(null)
  const [importOpen, setImportOpen] = useState(false)
  const [filtersOpen, setFiltersOpen] = useState(true)
  const [exportingXlsx, setExportingXlsx] = useState(false)
  const [exportingPdf, setExportingPdf] = useState(false)

  const activeFilterCount = [
    debouncedSearch,
    type,
    status,
    overdue ? '1' : '',
    bankAccountId,
    creditCardId,
    costCenterId,
    dueFrom || dueTo,
    paidFrom || paidTo,
  ].filter(Boolean).length

  const deleteAccount = useDeleteAccount()
  const cancelAccount = useCancelAccount()
  const settleAccount = useSettleAccount(toSettle?.id ?? '')

  const filters: AccountListParams = {
    search: debouncedSearch || undefined,
    type: type || undefined,
    status: overdue ? undefined : status || undefined,
    overdue: overdue || undefined,
    bank_account_id: bankAccountId || undefined,
    credit_card_id: creditCardId || undefined,
    cost_center_id: costCenterId || undefined,
    due_from: dueFrom || undefined,
    due_to: dueTo || undefined,
    paid_from: paidFrom || undefined,
    paid_to: paidTo || undefined,
  }

  const query = useAccountsQuery({
    ...filters,
    page,
    per_page: perPage,
    with_settlements: detailed || undefined,
  })

  const updateParams = (next: {
    page?: number
    search?: string
    type?: string
    status?: string
    overdue?: string
    bank_account_id?: string
    credit_card_id?: string
    cost_center_id?: string
    due_from?: string
    due_to?: string
    paid_from?: string
    paid_to?: string
  }) => {
    setSearchParams((params) => {
      if (next.type !== undefined) {
        next.type ? params.set('type', next.type) : params.delete('type')
        params.delete('page')
      }
      if (next.status !== undefined) {
        next.status ? params.set('status', next.status) : params.delete('status')
        if (next.status !== undefined) params.delete('overdue')
        params.delete('page')
      }
      if (next.overdue !== undefined) {
        if (next.overdue === '1') {
          params.set('overdue', '1')
          params.delete('status')
        } else {
          params.delete('overdue')
        }
        params.delete('page')
      }
      if (next.bank_account_id !== undefined) {
        next.bank_account_id ? params.set('bank_account_id', next.bank_account_id) : params.delete('bank_account_id')
        params.delete('page')
      }
      if (next.credit_card_id !== undefined) {
        next.credit_card_id ? params.set('credit_card_id', next.credit_card_id) : params.delete('credit_card_id')
        params.delete('page')
      }
      if (next.cost_center_id !== undefined) {
        next.cost_center_id ? params.set('cost_center_id', next.cost_center_id) : params.delete('cost_center_id')
        params.delete('page')
      }
      if (next.due_from !== undefined) {
        next.due_from ? params.set('due_from', next.due_from) : params.delete('due_from')
        params.delete('page')
      }
      if (next.due_to !== undefined) {
        next.due_to ? params.set('due_to', next.due_to) : params.delete('due_to')
        params.delete('page')
      }
      if (next.paid_from !== undefined) {
        next.paid_from ? params.set('paid_from', next.paid_from) : params.delete('paid_from')
        params.delete('page')
      }
      if (next.paid_to !== undefined) {
        next.paid_to ? params.set('paid_to', next.paid_to) : params.delete('paid_to')
        params.delete('page')
      }
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

  const toggleDetailedView = () => {
    setSearchParams((params) => {
      if (detailed) {
        params.delete('view')
      } else {
        params.set('view', 'detailed')
      }
      return params
    }, { replace: true })
  }

  const updatePerPage = (nextPerPage: number) => {
    setSearchParams((params) => {
      if (nextPerPage === DEFAULT_PER_PAGE) {
        params.delete('per_page')
      } else {
        params.set('per_page', String(nextPerPage))
      }
      params.delete('page')
      return params
    }, { replace: true })
  }

  const exportMetaLines = (): string[] => {
    const lines: string[] = []

    if (debouncedSearch) lines.push(`Busca: ${debouncedSearch}`)
    if (type) lines.push(`Tipo: ${type === 'receivable' ? 'a receber' : 'a pagar'}`)

    if (overdue) {
      lines.push('Situação: somente vencidos')
    } else if (status) {
      lines.push(`Status: ${STATUS_LABELS[status as Account['status']]?.label ?? status}`)
    }

    const bankAccount = bankAccounts.data?.find((option) => option.value === bankAccountId)
    if (bankAccount) lines.push(`Conta bancária: ${bankAccount.label}`)

    const creditCard = creditCards.data?.find((option) => option.value === creditCardId)
    if (creditCard) lines.push(`Cartão: ${creditCard.label}`)

    const costCenter = costCenters.data?.find((option) => option.value === costCenterId)
    if (costCenter) lines.push(`Centro de custo: ${costCenter.label}`)

    const dueRange = rangeLine('Vencimento', dueFrom, dueTo)
    if (dueRange) lines.push(dueRange)

    const paidRange = rangeLine('Data da baixa', paidFrom, paidTo)
    if (paidRange) lines.push(paidRange)

    return lines
  }

  const handleExportXlsx = async () => {
    setExportingXlsx(true)

    try {
      await accountsService.exportXlsx(filters)
    } catch (error) {
      toast.error(
        'Falha ao exportar',
        isApiError(error) ? error.message : 'Não foi possível gerar o arquivo XLSX.',
      )
    } finally {
      setExportingXlsx(false)
    }
  }

  const handleExportPdf = async () => {
    const win = openReportWindow()

    if (!win) {
      toast.error('Falha ao exportar', 'Permita pop-ups para gerar o PDF.')
      return
    }

    win.document.write(
      '<p style="font-family: Arial, sans-serif; padding: 24px; color: #4b5563;">Gerando PDF…</p>',
    )

    setExportingPdf(true)

    try {
      const accounts = await accountsService.listAll(filters)
      writeHtmlReport(win, 'Contas a pagar e receber', buildAccountsReportHtml(accounts, exportMetaLines()))
    } catch (error) {
      win.close()
      toast.error(
        'Falha ao exportar',
        isApiError(error) ? error.message : 'Não foi possível gerar o PDF.',
      )
    } finally {
      setExportingPdf(false)
    }
  }

  const emptyState = (
    <EmptyState
      icon={Banknote}
      title="Nenhum lançamento encontrado"
      description="Crie lançamentos de contas a pagar ou receber."
      action={
        <Can permission={Permission.ACCOUNTS_CREATE}>
          <ButtonLink to="/accounts/create">
            <Plus className="size-4" />
            Novo lançamento
          </ButtonLink>
        </Can>
      }
    />
  )

  const compactColumns: Array<Column<Account>> = [
    {
      key: 'description',
      header: 'Lançamento',
      render: (a) => {
        const categoryLabel = accountCategoryLabel(a)

        const meta = [
          categoryLabel,
          a.credit_card ? `Cartão: ${a.credit_card}` : null,
          !a.credit_card ? a.bank_account : null,
          a.counterparty,
        ].filter(Boolean)

        return (
          <div className="min-w-0">
            <div className="flex items-center gap-2">
              <p className="truncate font-medium text-foreground">{a.description}</p>
              {a.installment_number !== null && (
                <Badge variant="neutral">
                  {a.installment_number}/{a.installment_total}
                </Badge>
              )}
              {a.allocation_mode === 'split' && <Badge variant="primary">Rateio</Badge>}
            </div>
            <p className="truncate text-[13px] text-muted">{meta.length > 0 ? meta.join(' · ') : '—'}</p>
          </div>
        )
      },
    },
    {
      key: 'value',
      header: 'Valor',
      className: 'text-center',
      render: (a) => (
        <div className="inline-flex flex-col items-end text-right">
          <p className={`font-medium ${a.type === 'receivable' ? 'text-success' : 'text-foreground'}`}>
            {formatCurrency(a.value)}
          </p>
          {a.settled_amount > 0 && a.settled_amount < a.value && (
            <p className="text-[13px] text-muted">{formatCurrency(a.remaining_amount)} restante</p>
          )}
        </div>
      ),
    },
    {
      key: 'purchase_date',
      header: 'Data da compra',
      render: (a) => (
        <span className="text-muted">{formatDate(a.purchase_date ?? null)}</span>
      ),
    },
    {
      key: 'due_date',
      header: 'Vencimento',
      render: (a) => <span className="text-muted">{formatDate(a.due_date)}</span>,
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
      className: 'w-44 text-center',
      render: (a: Account) => (
        <AccountActions
          account={a}
          onSettle={setToSettle}
          onCancel={setToCancel}
          onDelete={setToDelete}
        />
      ),
    },
  ]

  const detailedColumns: Array<Column<Account>> = [
    {
      key: 'type',
      header: 'Tipo',
      className: 'w-24',
      render: (a) => (
        <Badge variant={a.type === 'receivable' ? 'success' : 'warning'}>
          {a.type === 'receivable' ? 'A receber' : 'A pagar'}
        </Badge>
      ),
    },
    {
      key: 'description',
      header: 'Lançamento',
      render: (a) => {
        const hasDetails = a.is_card_purchase || a.is_reconciled || Boolean(a.observation)

        return (
          <div className="min-w-0 max-w-64">
            <div className="flex items-center gap-1.5">
              <p className="truncate font-medium text-foreground" title={a.description}>
                {a.description}
              </p>
              {a.installment_number !== null && (
                <Badge variant="neutral">
                  {a.installment_number}/{a.installment_total}
                </Badge>
              )}
              {a.allocation_mode === 'split' && <Badge variant="primary">Rateio</Badge>}
            </div>
            {hasDetails && (
              <p className="mt-0.5 flex items-center gap-1.5 truncate text-[12px] text-muted">
                {a.is_card_purchase && (
                  <span className="inline-flex shrink-0" title="Compra no cartão">
                    <CreditCard className="size-3" />
                  </span>
                )}
                {a.is_reconciled && (
                  <span className="inline-flex shrink-0" title="Conciliado">
                    <CheckCircle2 className="size-3" />
                  </span>
                )}
                {a.observation && (
                  <span className="truncate" title={a.observation}>
                    {a.observation}
                  </span>
                )}
              </p>
            )}
          </div>
        )
      },
    },
    {
      key: 'counterparty',
      header: 'Cliente/Fornecedor',
      render: (a) => (
        <span className="block max-w-40 truncate text-muted" title={a.counterparty ?? undefined}>
          {a.counterparty || '—'}
        </span>
      ),
    },
    {
      key: 'category',
      header: 'Categoria',
      render: (a) => {
        const label = accountCategoryLabel(a)
        const isSplit = a.allocation_mode === 'split'

        return (
          <div className="max-w-40">
            <span className="flex items-center gap-1.5">
              {!isSplit && a.category && (
                <span
                  aria-hidden="true"
                  className="size-2.5 shrink-0 rounded-full"
                  style={{ backgroundColor: a.category.color ?? '#e2e8f0' }}
                />
              )}
              <span className="truncate text-muted" title={label || undefined}>
                {label || '—'}
              </span>
            </span>
            {!isSplit && a.subcategory?.name && (
              <span className="block truncate text-[12px] text-muted" title={a.subcategory.name}>
                {a.subcategory.name}
              </span>
            )}
          </div>
        )
      },
    },
    {
      key: 'cost_center',
      header: 'Centro de custo',
      render: (a) => (
        <span className="block max-w-36 truncate text-muted" title={a.cost_center ?? undefined}>
          {a.cost_center || '—'}
        </span>
      ),
    },
    {
      key: 'origin',
      header: 'Origem',
      render: (a) => {
        const name = a.credit_card || a.bank_account

        if (!name) return <span className="text-muted">—</span>

        return (
          <span className="flex max-w-40 items-center gap-1.5 text-muted" title={name}>
            {a.credit_card ? (
              <CreditCard className="size-3.5 shrink-0" />
            ) : (
              <Landmark className="size-3.5 shrink-0" />
            )}
            <span className="truncate">{name}</span>
          </span>
        )
      },
    },
    {
      key: 'value',
      header: 'Valor',
      className: 'text-right whitespace-nowrap',
      render: (a) => (
        <div className="text-right">
          <p className={`font-medium ${a.type === 'receivable' ? 'text-success' : 'text-foreground'}`}>
            {formatCurrency(a.value)}
          </p>
          {a.settled_amount > 0 && a.settled_amount < a.value && (
            <p className="text-[12px] text-muted">{formatCurrency(a.remaining_amount)} restante</p>
          )}
        </div>
      ),
    },
    {
      key: 'purchase_date',
      header: 'Compra',
      className: 'whitespace-nowrap',
      render: (a) => <span className="text-muted">{formatDate(a.purchase_date ?? null)}</span>,
    },
    {
      key: 'expected_date',
      header: 'Previsto',
      className: 'whitespace-nowrap',
      render: (a) => <span className="text-muted">{formatDate(a.expected_date)}</span>,
    },
    {
      key: 'due_date',
      header: 'Vencimento',
      className: 'whitespace-nowrap',
      render: (a) => {
        const overdue = isOverdue(a, today)

        return (
          <span className={cn('inline-flex items-center gap-1 tabular-nums', overdue ? 'font-medium text-danger' : 'text-muted')}>
            {overdue && <TriangleAlert className="size-3.5 shrink-0" />}
            {formatDate(a.due_date)}
          </span>
        )
      },
    },
    {
      key: 'paid_date',
      header: 'Baixa',
      className: 'whitespace-nowrap',
      render: (a) => {
        const settlements = a.settlements ?? []
        const lastSettlement = settlements[settlements.length - 1]

        return (
          <div className="text-muted">
            <p className="tabular-nums">{formatDate(a.paid_date)}</p>
            {lastSettlement?.method && <p className="text-[12px]">{lastSettlement.method}</p>}
          </div>
        )
      },
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
      className: 'w-44 text-center',
      render: (a: Account) => (
        <AccountActions
          account={a}
          onSettle={setToSettle}
          onCancel={setToCancel}
          onDelete={setToDelete}
        />
      ),
    },
  ]

  const columns = detailed ? detailedColumns : compactColumns

  return (
    <Page>
      <PageHeader
        title="Contas a pagar e receber"
        description="Gerencie os lançamentos financeiros da operação."
        breadcrumb={[{ label: 'Dashboard', to: '/dashboard' }, { label: 'Contas' }]}
        actions={
          <div className="flex flex-wrap items-center gap-2">
            <Button
              variant="secondary"
              onClick={handleExportXlsx}
              loading={exportingXlsx}
              disabled={query.isPending}
            >
              <Download className="size-4" />
              Exportar XLSX
            </Button>
            <Button
              variant="secondary"
              onClick={handleExportPdf}
              loading={exportingPdf}
              disabled={query.isPending}
            >
              <Printer className="size-4" />
              Exportar PDF
            </Button>
            <Button
              variant="secondary"
              onClick={toggleDetailedView}
              aria-pressed={detailed}
              title={
                detailed
                  ? 'Voltar para a visualização em lista'
                  : 'Exibir todos os detalhes de cada lançamento'
              }
            >
              {detailed ? <LayoutList className="size-4" /> : <Rows3 className="size-4" />}
              {detailed ? 'Visualização compacta' : 'Visualização detalhada'}
            </Button>
            <Can permission={Permission.ACCOUNTS_CREATE}>
              <Button variant="secondary" onClick={() => setImportOpen(true)}>
                <FileUp className="size-4" />
                Importar planilha
              </Button>
              <ButtonLink to="/accounts/create">
                <Plus className="size-4" />
                Novo lançamento
              </ButtonLink>
            </Can>
          </div>
        }
      />

      <PageContent>
        <Card>
          <button
            type="button"
            onClick={() => setFiltersOpen((open) => !open)}
            aria-expanded={filtersOpen}
            className={cn(
              'flex w-full cursor-pointer items-center justify-between gap-3 px-5 py-4 text-left transition-colors hover:bg-surface-2/40',
              filtersOpen ? 'rounded-t-xl' : 'rounded-xl',
            )}
          >
            <span className="flex items-center gap-2">
              <SlidersHorizontal className="size-4 text-muted" aria-hidden="true" />
              <span className="text-sm font-semibold text-foreground">Filtros</span>
              {activeFilterCount > 0 && <Badge variant="primary">{activeFilterCount}</Badge>}
            </span>
            <ChevronDown
              className={cn(
                'size-4 shrink-0 text-muted transition-transform duration-200',
                filtersOpen && 'rotate-180',
              )}
              aria-hidden="true"
            />
          </button>

          {filtersOpen && (
            <CardContent className="flex flex-col gap-4 border-t border-surface-2">
              <SearchInput
                placeholder="Buscar por descrição ou fornecedor..."
                aria-label="Buscar contas"
                value={search}
                onChange={(e) => {
                  setSearch(e.target.value)
                  updateParams({ search: e.target.value })
                }}
              />

              <div className="flex flex-col gap-3 xl:flex-row xl:items-center xl:justify-between">
                <SegmentedControl
                  value={type}
                  options={TYPE_OPTIONS}
                  onChange={(value) => updateParams({ type: value })}
                  className="max-w-md"
                />

                <div className="flex flex-wrap gap-1.5">
                  {STATUS_OPTIONS.map((option) => (
                    <button
                      key={option.value}
                      type="button"
                      onClick={() => updateParams({ status: option.value, overdue: '' })}
                      className={cn(
                        'rounded-full px-3.5 py-1.5 text-[13px] font-medium transition-colors',
                        !overdue && status === option.value
                          ? 'bg-primary text-primary-foreground'
                          : 'bg-surface-2 text-muted hover:bg-surface-3 hover:text-foreground',
                      )}
                    >
                      {option.label}
                    </button>
                  ))}
                  <button
                    type="button"
                    onClick={() => updateParams({ overdue: overdue ? '' : '1', status: '' })}
                    className={cn(
                      'rounded-full px-3.5 py-1.5 text-[13px] font-medium transition-colors',
                      overdue
                        ? 'bg-danger text-white'
                        : 'bg-surface-2 text-muted hover:bg-surface-3 hover:text-foreground',
                    )}
                  >
                    Vencidos
                  </button>
                </div>
              </div>

              <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                <label className="block min-w-0">
                  <span className="mb-1.5 block text-[13px] font-medium text-foreground">Conta bancária</span>
                  <Select
                    aria-label="Filtrar por conta bancária"
                    value={bankAccountId}
                    placeholder="Todas"
                    options={bankAccounts.data ?? []}
                    onChange={(e) => updateParams({ bank_account_id: e.target.value })}
                  />
                </label>

                <label className="block min-w-0">
                  <span className="mb-1.5 block text-[13px] font-medium text-foreground">Cartão de crédito</span>
                  <Select
                    aria-label="Filtrar por cartão de crédito"
                    value={creditCardId}
                    placeholder="Todos"
                    options={creditCards.data ?? []}
                    onChange={(e) => updateParams({ credit_card_id: e.target.value })}
                  />
                </label>

                <label className="block min-w-0">
                  <span className="mb-1.5 block text-[13px] font-medium text-foreground">Centro de custo</span>
                  <Select
                    aria-label="Filtrar por centro de custo"
                    value={costCenterId}
                    placeholder="Todos"
                    options={costCenters.data ?? []}
                    onChange={(e) => updateParams({ cost_center_id: e.target.value })}
                  />
                </label>
              </div>

              <div className="flex flex-col gap-3 lg:flex-row">
                <div className="flex-1">
                  <DateRangeFilter
                    label="Vencimento:"
                    from={dueFrom}
                    to={dueTo}
                    showClear
                    onChange={({ from, to }) => {
                      setDueFrom(from)
                      setDueTo(to)
                      updateParams({ due_from: from, due_to: to })
                    }}
                  />
                </div>

                <div className="flex-1">
                  <DateRangeFilter
                    label="Data da baixa:"
                    from={paidFrom}
                    to={paidTo}
                    showClear
                    onChange={({ from, to }) => {
                      setPaidFrom(from)
                      setPaidTo(to)
                      updateParams({ paid_from: from, paid_to: to })
                    }}
                  />
                </div>
              </div>
            </CardContent>
          )}
        </Card>

        <DataTable
          caption="Lista de contas"
          columns={columns}
          rows={query.data?.data ?? []}
          rowKey={(a) => a.id}
          loading={query.isPending}
          dense={detailed}
          emptyState={emptyState}
          renderExpanded={detailed ? (account) => <AccountDetailPanel account={account} /> : undefined}
        />

        {query.data && (
          <Pagination
            meta={query.data.meta}
            onPageChange={(next) => updateParams({ page: next })}
            perPage={perPage}
            onPerPageChange={updatePerPage}
            perPageOptions={PER_PAGE_OPTIONS}
          />
        )}
      </PageContent>

      <SettleDialog
        account={toSettle}
        open={toSettle !== null}
        submitting={settleAccount.isPending}
        onClose={() => setToSettle(null)}
        onConfirm={async (payload) => {
          await settleAccount.mutateAsync(payload)
          setToSettle(null)
        }}
      />

      <ImportAccountsDialog open={importOpen} onClose={() => setImportOpen(false)} />

      <ConfirmDialog
        open={toCancel !== null}
        onClose={() => setToCancel(null)}
        onConfirm={() => {
          if (toCancel) cancelAccount.mutate(toCancel.id, { onSettled: () => setToCancel(null) })
        }}
        loading={cancelAccount.isPending}
        title="Cancelar lançamento"
        description={<>Tem certeza que deseja cancelar <strong>{toCancel?.description}</strong>?</>}
        confirmLabel="Cancelar lançamento"
      />

      <ConfirmDialog
        open={toDelete !== null}
        onClose={() => setToDelete(null)}
        onConfirm={() => {
          if (toDelete) deleteAccount.mutate(toDelete.id, { onSettled: () => setToDelete(null) })
        }}
        loading={deleteAccount.isPending}
        title="Excluir lançamento"
        description={<>Tem certeza que deseja excluir <strong>{toDelete?.description}</strong>?</>}
        confirmLabel="Excluir"
      />
    </Page>
  )
}
