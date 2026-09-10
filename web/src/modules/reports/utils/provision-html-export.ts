import { formatCurrency } from '@/shared/utils/format'
import { escapeHtml } from '@/shared/utils/report-export'
import type { ProvisionReport } from '../services/reports.service'
import { formatProvisionAmount } from './provision-format'

function cell(value: number | null | undefined): string {
  return escapeHtml(formatProvisionAmount(value))
}

function columnHeaderRow(headers: string[]): string {
  return `<tr class="column-header-muted">${headers
    .map(
      (header, index) =>
        `<th class="${index === 0 ? '' : 'amount'}">${escapeHtml(header)}</th>`,
    )
    .join('')}</tr>`
}

export function buildProvisionMatrixHtml(data: ProvisionReport, title: string, subtitle: string): string {
  const headers = ['Conta', ...data.columns.map((column) => column.label), 'Total']
  const sections: string[] = []

  for (const group of data.groups) {
    sections.push(
      `<tr class="section-banner"><td colspan="${headers.length}">${escapeHtml(group.bank_account)}</td></tr>`,
    )
    sections.push(columnHeaderRow(headers))

    for (const row of group.rows) {
      const cells = [escapeHtml(row.description), ...data.columns.map((column) => cell(row.amounts[column.key])), '']

      sections.push(
        `<tr>${cells
          .map((value, index) => `<td class="${index === 0 ? '' : 'amount'}">${value}</td>`)
          .join('')}</tr>`,
      )
    }

    const subtotalCells = [
      'Subtotal',
      ...data.columns.map((column) => cell(group.subtotal.amounts[column.key])),
      cell(group.subtotal.total),
    ]

    sections.push(
      `<tr class="subtotal-row">${subtotalCells
        .map((value, index) => `<td class="${index === 0 ? '' : 'amount'}">${value}</td>`)
        .join('')}</tr>`,
    )
    sections.push(`<tr class="spacer"><td colspan="${headers.length}"></td></tr>`)
  }

  sections.push(`<tr class="section-banner"><td colspan="${headers.length}">TOTAL GERAL</td></tr>`)
  sections.push(columnHeaderRow(headers))

  const grandCells = [
    'Total geral',
    ...data.columns.map((column) => cell(data.grand_total.amounts[column.key])),
    cell(data.grand_total.total),
  ]

  sections.push(
    `<tr class="total-row-grand">${grandCells
      .map((value, index) => `<td class="${index === 0 ? '' : 'amount'}">${value}</td>`)
      .join('')}</tr>`,
  )

  const subtitleLines = subtitle
    .split(' · ')
    .map((line) => line.trim())
    .filter(Boolean)

  const summaryLine = `Total a receber: ${formatCurrency(data.total_in)} | Total a pagar: ${formatCurrency(data.total_out)} | Saldo líquido: ${formatCurrency(data.grand_total.total)}`

  return `
    <h1 class="report-title">${escapeHtml(title)}</h1>
    ${subtitleLines.map((line) => `<p class="report-subtitle">${escapeHtml(line)}</p>`).join('')}
    <p class="report-summary-line">${escapeHtml(summaryLine)}</p>
    <table class="report-table">
      <tbody>
        ${sections.join('')}
      </tbody>
    </table>
  `
}
