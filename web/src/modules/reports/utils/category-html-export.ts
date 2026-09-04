import { formatDate } from '@/shared/utils/format'
import { escapeHtml } from '@/shared/utils/report-export'
import type { CategoryMatrix, CategoryMatrixTotals } from '../services/reports.service'
import { formatCategoryAmount } from './category-format'

function cell(value: number | null | undefined): string {
  return escapeHtml(formatCategoryAmount(value))
}

function amountCells(totals: CategoryMatrixTotals, matrix: CategoryMatrix, highlight = false): string {
  const values = [
    ...matrix.columns.map((column) => cell(totals.amounts[column.key])),
    cell(totals.total),
  ]

  return values
    .map((value, index) => {
      const isTotal = index === values.length - 1
      const className = highlight || isTotal ? 'amount' : 'amount'
      return `<td class="${className}">${value}</td>`
    })
    .join('')
}

function totalRow(
  label: string,
  totals: CategoryMatrixTotals,
  matrix: CategoryMatrix,
  rowClass: string,
  indentPx = 8,
): string {
  return `<tr class="${rowClass}">
    <td style="padding-left:${indentPx}px">${escapeHtml(label)}</td>
    ${amountCells(totals, matrix, rowClass.includes('total-row'))}
  </tr>`
}

export function buildCategoryMatrixHtml(
  matrix: CategoryMatrix,
  title: string,
  subtitle: string,
): string {
  const headers = ['Descrição', ...matrix.columns.map((column) => column.label), 'Total geral']
  const sections: string[] = []

  for (const group of matrix.groups) {
    sections.push(
      `<tr class="section-banner"><td colspan="${headers.length}">${escapeHtml(group.cost_center)}</td></tr>`,
    )

    for (const category of group.categories) {
      sections.push(totalRow(`${category.category} - Totais`, category.subtotal, matrix, 'subtotal-row'))

      for (const subcategory of category.subcategories) {
        sections.push(
          totalRow(`${subcategory.subcategory} - Totais`, subcategory.subtotal, matrix, 'subtotal-row-soft', 24),
        )
      }
    }

    sections.push(totalRow(`${group.cost_center} - Totais`, group.subtotal, matrix, 'total-row'))
    sections.push(`<tr class="spacer"><td colspan="${headers.length}"></td></tr>`)
  }

  sections.push(totalRow('Total geral', matrix.grand_total, matrix, 'total-row-strong'))

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
      <div class="report-summary-row"><span>Total geral do período</span><strong>${cell(matrix.grand_total.total)}</strong></div>
      <div class="report-summary-row"><span>Gerado em</span><span>${escapeHtml(formatDate(new Date().toISOString()))}</span></div>
    </div>
  `
}
