import { escapeHtml } from '@/shared/utils/report-export'

export interface ReportHtmlSection {
  title?: string
  headers: string[]
  rows: Array<Array<string | number>>
  amountColumns?: number[]
  footer?: { label: string; value: string }
  /** When true, uses muted gray banner like payables XLSX sections. */
  mutedBanner?: boolean
}

export interface ReportHtmlOptions {
  title: string
  subtitle?: string
  sections: ReportHtmlSection[]
  summary?: Array<{ label: string; value: string }>
}

function cellClass(amountColumns: number[] | undefined, index: number): string {
  return amountColumns?.includes(index) ? ' class="amount"' : ''
}

export function buildReportHtml({ title, subtitle, sections, summary }: ReportHtmlOptions): string {
  const sectionsHtml = sections
    .map((section) => {
      const columnCount = Math.max(section.headers.length, 1)
      const bannerClass = section.mutedBanner ? 'section-banner-muted' : 'section-banner'
      const banner = section.title
        ? `<tr class="${bannerClass}"><td colspan="${columnCount}">${escapeHtml(section.title)}</td></tr>`
        : ''

      const headerRow = `<tr class="column-header">${section.headers
        .map((header, index) => `<th${cellClass(section.amountColumns, index)}>${escapeHtml(header)}</th>`)
        .join('')}</tr>`

      const bodyRows = section.rows
        .map((row) => {
          const cells = row
            .map((cell, index) => `<td${cellClass(section.amountColumns, index)}>${escapeHtml(cell)}</td>`)
            .join('')
          return `<tr>${cells}</tr>`
        })
        .join('')

      const footerRow = section.footer
        ? `<tr class="footer-row"><td colspan="${Math.max(columnCount - 1, 1)}">${escapeHtml(section.footer.label)}</td><td class="amount">${escapeHtml(section.footer.value)}</td></tr>`
        : ''

      return `<table class="report-table"><tbody>${banner}${headerRow}${bodyRows}${footerRow}</tbody></table>`
    })
    .join('')

  const summaryHtml =
    summary && summary.length > 0
      ? `<div class="report-summary">${summary
          .map(
            (item) =>
              `<div class="report-summary-row"><span>${escapeHtml(item.label)}</span><strong>${escapeHtml(item.value)}</strong></div>`,
          )
          .join('')}</div>`
      : ''

  const subtitleLines = (subtitle ?? '')
    .split(' · ')
    .map((line) => line.trim())
    .filter(Boolean)

  return `
    <h1 class="report-title">${escapeHtml(title)}</h1>
    ${subtitleLines.map((line) => `<p class="report-subtitle">${escapeHtml(line)}</p>`).join('')}
    ${sectionsHtml}
    ${summaryHtml}
  `
}
