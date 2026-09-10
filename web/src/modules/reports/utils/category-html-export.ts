import { formatCurrency } from '@/shared/utils/format'
import { escapeHtml } from '@/shared/utils/report-export'
import type { CategoryMatrix, CategoryMatrixTotals } from '../services/reports.service'
import { formatCategoryAmount } from './category-format'

function cell(value: number | null | undefined): string {
  return escapeHtml(formatCategoryAmount(value))
}

function amountCells(totals: CategoryMatrixTotals, matrix: CategoryMatrix): string {
  const values = [...matrix.columns.map((column) => cell(totals.amounts[column.key])), cell(totals.total)]

  return values
    .map((value, index) => {
      const isTotal = index === values.length - 1
      return `<td class="amount${isTotal ? ' amount-danger' : ''}">${value}</td>`
    })
    .join('')
}

function totalRow(
  label: string,
  totals: CategoryMatrixTotals,
  matrix: CategoryMatrix,
  rowClass: string,
  indentSpaces = 0,
): string {
  const indent = indentSpaces > 0 ? '&nbsp;'.repeat(indentSpaces) : ''
  return `<tr class="${rowClass}">
    <td>${indent}${escapeHtml(label)}</td>
    ${amountCells(totals, matrix)}
  </tr>`
}

export function buildCategoryMatrixHtml(matrix: CategoryMatrix, title: string, subtitle: string): string {
  const headers = ['Descrição', ...matrix.columns.map((column) => column.label), 'Total geral']
  const sections: string[] = []

  for (const group of matrix.groups) {
    sections.push(
      `<tr class="section-banner"><td colspan="${headers.length}">${escapeHtml(group.bank_account)}</td></tr>`,
    )

    for (const category of group.categories) {
      sections.push(totalRow(`${category.category} - Totais`, category.subtotal, matrix, 'subtotal-row'))

      for (const subcategory of category.subcategories) {
        sections.push(
          totalRow(subcategory.subcategory, subcategory.subtotal, matrix, 'subtotal-row-soft', 4),
        )
      }
    }

    sections.push(totalRow(`${group.bank_account} - Totais`, group.subtotal, matrix, 'subtotal-row-group'))
    sections.push(`<tr class="spacer"><td colspan="${headers.length}"></td></tr>`)
  }

  sections.push(totalRow('Total geral', matrix.grand_total, matrix, 'total-row-grand'))

  const subtitleLines = subtitle
    .split(' · ')
    .map((line) => line.trim())
    .filter(Boolean)

  const summaryLine = `Total geral: ${formatCurrency(matrix.grand_total.total)}`

  return `
    <h1 class="report-title">${escapeHtml(title)}</h1>
    ${subtitleLines.map((line) => `<p class="report-subtitle">${escapeHtml(line)}</p>`).join('')}
    <p class="report-summary-line">${escapeHtml(summaryLine)}</p>
    <table class="report-table">
      <thead>
        <tr class="column-header">${headers
          .map(
            (header, index) =>
              `<th class="${index === 0 ? '' : index === headers.length - 1 ? 'amount amount-danger' : 'amount'}">${escapeHtml(header)}</th>`,
          )
          .join('')}</tr>
      </thead>
      <tbody>
        ${sections.join('')}
      </tbody>
    </table>
  `
}
