import { formatDate } from '@/shared/utils/format'
import { escapeHtml } from '@/shared/utils/report-export'
import type { MonthlySummaryReport } from '../services/reports.service'
import { formatCategoryAmount } from './category-format'

function cell(value: number | null | undefined): string {
  return escapeHtml(formatCategoryAmount(value))
}

export function buildMonthlySummaryHtml(data: MonthlySummaryReport, title: string, subtitle: string): string {
  const headers = ['Centro de custo', ...data.columns.map((column) => column.label), 'Total']
  const sections: string[] = []

  for (const row of data.rows) {
    const cells = [
      escapeHtml(row.cost_center),
      ...data.columns.map((column) => cell(row.amounts[column.key])),
      cell(row.total),
    ]

    sections.push(
      `<tr>${cells
        .map((value, index) => `<td class="${index === 0 ? '' : 'amount'}"${index === 0 ? ' style="font-weight:600"' : ''}>${value}</td>`)
        .join('')}</tr>`,
    )
  }

  const totalCells = [
    'Total',
    ...data.columns.map((column) => cell(data.grand_total.amounts[column.key])),
    cell(data.grand_total.total),
  ]

  sections.push(
    `<tr class="total-row">${totalCells
      .map((value, index) => `<td class="${index === 0 ? '' : 'amount'}">${value}</td>`)
      .join('')}</tr>`,
  )

  const averageColspan = headers.length - 1

  sections.push(
    `<tr class="average-row">
      <td colspan="${averageColspan}">Média mês</td>
      <td class="amount">${cell(data.monthly_average)}</td>
    </tr>`,
  )

  const subtitleLines = subtitle
    .split(' · ')
    .map((line) => line.trim())
    .filter(Boolean)

  return `
    <h1 class="report-title">${escapeHtml(title)}</h1>
    ${subtitleLines.map((line) => `<p class="report-subtitle">${escapeHtml(line)}</p>`).join('')}
    <table class="report-table">
      <thead>
        <tr class="column-header">${headers
          .map(
            (header, index) =>
              `<th class="${index === 0 ? '' : 'amount'}">${escapeHtml(header)}</th>`,
          )
          .join('')}</tr>
      </thead>
      <tbody>
        ${sections.join('')}
      </tbody>
    </table>
    <div class="report-summary">
      <div class="report-summary-row"><span>Total geral do período</span><strong>${cell(data.grand_total.total)}</strong></div>
      <div class="report-summary-row"><span>Média mês</span><strong>${cell(data.monthly_average)}</strong></div>
      <div class="report-summary-row"><span>Gerado em</span><span>${escapeHtml(formatDate(new Date().toISOString()))}</span></div>
    </div>
  `
}
