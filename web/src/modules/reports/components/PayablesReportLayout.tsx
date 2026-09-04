import { cn } from '@/shared/utils/cn'
import { formatCurrency, formatShortDate } from '@/shared/utils/format'
import type { PayableAccount, PayablesExportReport } from '../services/reports.service'

interface PayablesReportLayoutProps {
  data: PayablesExportReport
  compact?: boolean
  className?: string
}

const HEADERS = ['Data', 'Descrição', 'Valor'] as const
const COL_WIDTHS = ['16%', '60%', '24%'] as const

export function PayablesReportLayout({ data, compact = false, className }: PayablesReportLayoutProps) {
  const cellClass = compact ? 'px-2 py-1 text-[11px]' : 'px-3 py-2 text-sm'
  const headerClass = compact ? 'px-2 py-1.5 text-[10px]' : 'px-3 py-2.5 text-xs'
  const referenceDate = formatShortDate(data.reference_date)

  return (
    <div className={cn('space-y-8', className)}>
      {data.groups.map((group) => (
        <section key={group.cost_center} className="overflow-x-auto">
          <div className={cn('rounded-t-lg bg-surface-2 px-4 py-3 font-bold uppercase tracking-wide text-foreground', compact ? 'text-xs' : 'text-sm')}>
            {group.cost_center}
          </div>

          <table className="w-full table-fixed border-collapse">
            <colgroup>
              {COL_WIDTHS.map((width, index) => (
                <col key={HEADERS[index]} style={{ width }} />
              ))}
            </colgroup>
            <thead>
              <tr className="border-b border-surface-3 bg-surface-2/50 uppercase tracking-wide text-muted">
                {HEADERS.map((header, index) => (
                  <th
                    key={header}
                    className={cn(
                      headerClass,
                      'font-semibold',
                      index === HEADERS.length - 1 ? 'text-right' : 'text-left',
                    )}
                  >
                    {header}
                  </th>
                ))}
              </tr>
            </thead>
            <tbody>
              {group.overdue.accounts.length > 0 && (
                <>
                  <SectionTitle colSpan={HEADERS.length} label="EM ATRASO" tone="danger" cellClass={cellClass} centered />
                  {group.overdue.accounts.map((account) => (
                    <AccountRow key={account.id} account={account} cellClass={cellClass} />
                  ))}
                  <TotalRow
                    colSpan={2}
                    label="TOTAL EM ATRASO"
                    value={group.overdue.total}
                    cellClass={cellClass}
                    tone="danger"
                  />
                </>
              )}

              {group.due_today.accounts.length > 0 && (
                <>
                  <SectionTitle
                    colSpan={HEADERS.length}
                    label={`PAGOS EM ${referenceDate}`}
                    tone="success"
                    cellClass={cellClass}
                    centered
                  />
                  {group.due_today.accounts.map((account) => (
                    <AccountRow key={account.id} account={account} cellClass={cellClass} />
                  ))}
                  <TotalRow colSpan={2} label="TOTAL PAGO" value={group.due_today.total} cellClass={cellClass} tone="success" />
                </>
              )}
            </tbody>
          </table>
        </section>
      ))}

      <GeneralSummary data={data} compact={compact} referenceDate={referenceDate} />
    </div>
  )
}

function SectionTitle({
  label,
  tone,
  colSpan,
  cellClass,
  centered = false,
}: {
  label: string
  tone: 'danger' | 'success'
  colSpan: number
  cellClass: string
  centered?: boolean
}) {
  return (
    <tr>
      <td
        colSpan={colSpan}
        className={cn(
          cellClass,
          'pt-3 font-bold uppercase',
          centered && 'text-center',
          tone === 'danger' ? 'text-danger' : 'text-success',
        )}
      >
        {label}
      </td>
    </tr>
  )
}

function AccountRow({ account, cellClass }: { account: PayableAccount; cellClass: string }) {
  return (
    <tr className="border-b border-surface-3/60 text-foreground">
      <td className={cn(cellClass, 'whitespace-nowrap text-left tabular-nums')}>{formatShortDate(account.due_date)}</td>
      <td className={cn(cellClass, 'text-left')}>{account.description}</td>
      <td className={cn(cellClass, 'whitespace-nowrap text-right font-medium tabular-nums')}>
        {formatCurrency(account.remaining_amount)}
      </td>
    </tr>
  )
}

function TotalRow({
  label,
  value,
  colSpan,
  cellClass,
  tone,
}: {
  label: string
  value: number
  colSpan: number
  cellClass: string
  tone: 'danger' | 'success'
}) {
  const toneClass = tone === 'danger' ? 'text-danger' : 'text-success'

  return (
    <tr className={cn('bg-surface-2/60 font-bold', toneClass)}>
      <td colSpan={colSpan} className={cn(cellClass, 'uppercase')}>
        {label}
      </td>
      <td className={cn(cellClass, 'whitespace-nowrap text-right tabular-nums')}>{formatCurrency(value)}</td>
    </tr>
  )
}

function GeneralSummary({
  data,
  compact,
  referenceDate,
}: {
  data: PayablesExportReport
  compact: boolean
  referenceDate: string
}) {
  const cellClass = compact ? 'px-2 py-1 text-[11px]' : 'px-3 py-2 text-sm'
  const headerClass = compact ? 'px-2 py-1.5 text-[10px]' : 'px-3 py-2.5 text-xs'

  return (
    <section className="overflow-x-auto rounded-xl border-2 border-surface-3 bg-surface-2/30 p-4">
      <h3 className={cn('mb-4 text-center font-bold uppercase text-foreground', compact ? 'text-xs' : 'text-sm')}>
        Resumo geral em {referenceDate}
      </h3>

      <div className="grid gap-6 lg:grid-cols-2">
        <SummaryTable
          title={data.summary.paid_today.title}
          rows={data.summary.paid_today.rows}
          totalLabel="TOTAL PAGOS"
          total={data.summary.paid_today.total}
          tone="success"
          cellClass={cellClass}
          headerClass={headerClass}
        />
        <SummaryTable
          title={data.summary.overdue.title}
          rows={data.summary.overdue.rows}
          totalLabel="TOTAL EM ATRASO"
          total={data.summary.overdue.total}
          tone="danger"
          cellClass={cellClass}
          headerClass={headerClass}
        />
      </div>
    </section>
  )
}

function SummaryTable({
  title,
  rows,
  totalLabel,
  total,
  tone,
  cellClass,
  headerClass,
}: {
  title: string
  rows: Array<{ cost_center: string; amount: number }>
  totalLabel: string
  total: number
  tone: 'danger' | 'success'
  cellClass: string
  headerClass: string
}) {
  const toneClass = tone === 'danger' ? 'text-danger' : 'text-success'

  return (
    <div className="overflow-x-auto">
      <p className={cn('mb-2 font-bold uppercase', toneClass, headerClass)}>{title}</p>
      <table className="w-full table-fixed border-collapse">
        <colgroup>
          <col style={{ width: '70%' }} />
          <col style={{ width: '30%' }} />
        </colgroup>
        <thead>
          <tr className="border-b border-surface-3 uppercase tracking-wide text-muted">
            <th className={cn(headerClass, 'text-left font-semibold')}>Centro de custo</th>
            <th className={cn(headerClass, 'text-right font-semibold')}>Valor</th>
          </tr>
        </thead>
        <tbody>
          {rows.map((row) => (
            <tr key={row.cost_center} className="border-b border-surface-3/60">
              <td className={cn(cellClass, 'text-left text-foreground')}>{row.cost_center}</td>
              <td className={cn(cellClass, 'whitespace-nowrap text-right tabular-nums text-foreground')}>
                {formatCurrency(row.amount)}
              </td>
            </tr>
          ))}
          <tr className={cn('font-bold', toneClass)}>
            <td className={cn(cellClass, 'uppercase')}>{totalLabel}</td>
            <td className={cn(cellClass, 'whitespace-nowrap text-right tabular-nums')}>{formatCurrency(total)}</td>
          </tr>
        </tbody>
      </table>
    </div>
  )
}
