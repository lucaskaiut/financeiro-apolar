import { buildReportHtml } from '@/modules/reports/utils/report-html-export'
import { formatCurrency, formatDate } from '@/shared/utils/format'
import type { Account } from '@/shared/types/models'

const HEADERS = [
  'Tipo',
  'Descrição',
  'Cliente/Fornecedor',
  'Categoria',
  'Subcategoria',
  'Centro de custo',
  'Conta bancária',
  'Cartão',
  'Compra',
  'Previsto',
  'Vencimento',
  'Baixa',
  'Status',
  'Observação',
  'Valor',
  'Liquidado',
  'Restante',
]

const AMOUNT_COLUMNS = [14, 15, 16]

function categoryLabel(account: Account): string {
  if (account.allocation_mode === 'split' && account.allocations?.length) {
    return account.allocations
      .map((line) => line.category?.name)
      .filter(Boolean)
      .filter((name, index, names) => names.indexOf(name) === index)
      .join(' / ')
  }

  return account.category?.name ?? ''
}

function date(value: string | null | undefined): string {
  return value ? formatDate(value) : ''
}

function row(account: Account): Array<string | number> {
  const isSplit = account.allocation_mode === 'split'

  return [
    account.type === 'receivable' ? 'A receber' : 'A pagar',
    account.description,
    account.counterparty ?? '',
    categoryLabel(account),
    isSplit ? '' : (account.subcategory?.name ?? ''),
    account.cost_center ?? '',
    account.bank_account ?? '',
    account.credit_card ?? '',
    date(account.purchase_date),
    date(account.expected_date),
    date(account.due_date),
    date(account.paid_date),
    account.status_label,
    account.observation ?? '',
    formatCurrency(account.value),
    formatCurrency(account.settled_amount),
    formatCurrency(account.remaining_amount),
  ]
}

export function buildAccountsReportHtml(accounts: Account[], metaLines: string[]): string {
  const totalValue = accounts.reduce((sum, account) => sum + Number(account.value), 0)
  const totalSettled = accounts.reduce((sum, account) => sum + Number(account.settled_amount), 0)
  const totalRemaining = accounts.reduce((sum, account) => sum + Number(account.remaining_amount), 0)

  return buildReportHtml({
    title: 'Contas a pagar e receber',
    metaLines,
    sections: [
      {
        headers: HEADERS,
        rows: accounts.map(row),
        amountColumns: AMOUNT_COLUMNS,
      },
    ],
    totalRows: [
      {
        label: `Totais (${accounts.length} ${accounts.length === 1 ? 'lançamento' : 'lançamentos'})`,
        values: [
          formatCurrency(totalValue),
          formatCurrency(totalSettled),
          formatCurrency(totalRemaining),
        ],
        variant: 'total',
      },
    ],
    totalColumns: HEADERS.length,
  })
}
