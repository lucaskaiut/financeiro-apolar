import { escapeHtml } from '@/shared/utils/report-export'

export interface ReportHtmlFooter {
  label: string
  value?: string
  /** Extra amount cells after the label (XLSX multi-column total rows). */
  values?: string[]
  /** Matches applyXlsxFooterRow / TotalRow / colored payables footers. */
  variant?: 'footer' | 'total' | 'total-grand' | 'overdue' | 'paid'
}

export interface ReportHtmlSection {
  title?: string
  /** Bold label row without banner fill (e.g. "Pagamentos realizados"). */
  subtitle?: string
  headers?: string[]
  rows?: Array<Array<string | number>>
  amountColumns?: number[]
  /** First N columns are labels (left); used for labelBold. */
  labelColumns?: number
  labelBold?: boolean
  mutedBanner?: boolean
  /** Gray header fill #F3F4F6 like nested XLSX tables. */
  mutedHeader?: boolean
  /** Hide column header row. */
  hideHeader?: boolean
  footer?: ReportHtmlFooter
  footers?: ReportHtmlFooter[]
}

export interface ReportHtmlOptions {
  title: string
  subtitle?: string
  /** Extra gray lines under title (same as XLSX title block). */
  metaLines?: string[]
  /** Optional summary line in the title block (XLSX applyXlsxTitleBlock summary). */
  summaryLine?: string
  sections: ReportHtmlSection[]
  /** Grand total rows after all sections (same table styling as total-row). */
  totalRows?: ReportHtmlFooter[]
  totalColumns?: number
}

function cellClass(amountColumns: number[] | undefined, index: number, labelBold?: boolean, labelColumns = 1): string {
  const classes: string[] = []
  if (amountColumns?.includes(index)) classes.push('amount')
  if (labelBold && index < labelColumns) classes.push('label-bold')
  return classes.length > 0 ? ` class="${classes.join(' ')}"` : ''
}

function footerClass(variant: ReportHtmlFooter['variant'] = 'footer'): string {
  switch (variant) {
    case 'total':
      return 'total-row'
    case 'total-grand':
      return 'total-row-grand'
    case 'overdue':
      return 'footer-overdue'
    case 'paid':
      return 'footer-paid'
    default:
      return 'footer-row'
  }
}

function renderFooter(footer: ReportHtmlFooter, columnCount: number): string {
  const amounts = footer.values ?? (footer.value !== undefined ? [footer.value] : [])
  const labelSpan = Math.max(columnCount - Math.max(amounts.length, 1), 1)
  const amountCells =
    amounts.length > 0
      ? amounts.map((amount) => `<td class="amount">${escapeHtml(amount)}</td>`).join('')
      : `<td class="amount"></td>`

  return `<tr class="${footerClass(footer.variant)}"><td colspan="${labelSpan}">${escapeHtml(footer.label)}</td>${amountCells}</tr>`
}

function renderSection(section: ReportHtmlSection): string {
  const headers = section.headers ?? []
  const rows = section.rows ?? []
  const columnCount = Math.max(headers.length, ...rows.map((row) => row.length), 1)
  const labelColumns = section.labelColumns ?? 1
  const parts: string[] = []

  if (section.title) {
    const bannerClass = section.mutedBanner ? 'section-banner-muted' : 'section-banner'
    parts.push(`<tr class="${bannerClass}"><td colspan="${columnCount}">${escapeHtml(section.title)}</td></tr>`)
  }

  if (section.subtitle) {
    parts.push(`<tr class="section-subtitle"><td colspan="${columnCount}">${escapeHtml(section.subtitle)}</td></tr>`)
  }

  if (!section.hideHeader && headers.length > 0) {
    const headerClass = section.mutedHeader ? 'column-header-muted' : 'column-header'
    parts.push(
      `<tr class="${headerClass}">${headers
        .map((header, index) => `<th${cellClass(section.amountColumns, index)}>${escapeHtml(header)}</th>`)
        .join('')}</tr>`,
    )
  }

  for (const row of rows) {
    const cells = Array.from({ length: columnCount }, (_, index) => {
      const value = row[index] ?? ''
      return `<td${cellClass(section.amountColumns, index, section.labelBold, labelColumns)}>${escapeHtml(value)}</td>`
    }).join('')
    parts.push(`<tr>${cells}</tr>`)
  }

  const footers = section.footers ?? (section.footer ? [section.footer] : [])
  for (const footer of footers) {
    parts.push(renderFooter(footer, columnCount))
  }

  return parts.join('')
}

/** Builds report body HTML matching PhpSpreadsheet XLSX layout helpers. */
export function buildReportHtml({
  title,
  subtitle,
  metaLines,
  summaryLine,
  sections,
  totalRows,
  totalColumns,
}: ReportHtmlOptions): string {
  const subtitleLines = [
    ...(metaLines ?? []),
    ...((subtitle ?? '')
      .split(' · ')
      .map((line) => line.trim())
      .filter(Boolean)),
  ]

  const sectionHtml = sections.map(renderSection).join('')

  const inferredColumns =
    totalColumns ??
    Math.max(
      1,
      ...sections.map((section) =>
        Math.max(section.headers?.length ?? 0, ...(section.rows ?? []).map((row) => row.length), 1),
      ),
    )

  const totalsHtml =
    totalRows && totalRows.length > 0
      ? totalRows.map((row) => renderFooter(row, inferredColumns)).join('')
      : ''

  return `
    <h1 class="report-title">${escapeHtml(title)}</h1>
    ${subtitleLines.map((line) => `<p class="report-subtitle">${escapeHtml(line)}</p>`).join('')}
    ${summaryLine ? `<p class="report-summary-line">${escapeHtml(summaryLine)}</p>` : ''}
    <table class="report-table">
      <tbody>
        ${sectionHtml}
        ${totalsHtml}
      </tbody>
    </table>
  `
}
