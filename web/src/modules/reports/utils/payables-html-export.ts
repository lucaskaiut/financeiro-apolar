import { formatCurrency, formatShortDate } from '@/shared/utils/format'
import { escapeHtml } from '@/shared/utils/report-export'
import type { PayableAccount, PayablesExportReport, PayablesReport } from '../services/reports.service'
import { isPayablesReportOverdue } from './payables-report'

const NO_COST_CENTER = 'Sem centro de custo'

function costCenterName(value: string | null | undefined): string {
  return value?.trim() || NO_COST_CENTER
}

export function buildPayablesExportReport(data: PayablesReport, selectedIds: Set<string>): PayablesExportReport {
  const groupsMap = new Map<string, PayablesExportReport['groups'][number]>()

  for (const account of data.accounts) {
    const key = costCenterName(account.cost_center)

    if (!groupsMap.has(key)) {
      groupsMap.set(key, {
        cost_center: key,
        cost_center_id: account.cost_center_id,
        overdue: { accounts: [], total: 0 },
        due_today: { accounts: [], total: 0 },
        total_overdue: 0,
        total_paid_today: 0,
      })
    }

    const group = groupsMap.get(key)!

    if (isPayablesReportOverdue(account, selectedIds)) {
      group.overdue.accounts.push(account)
      group.overdue.total += account.remaining_amount
      group.total_overdue += account.remaining_amount
    }

    if (selectedIds.has(account.id)) {
      group.due_today.accounts.push(account)
      group.due_today.total += account.remaining_amount
      group.total_paid_today += account.remaining_amount
    }
  }

  const groups = Array.from(groupsMap.values())
    .filter((group) => group.overdue.accounts.length > 0 || group.due_today.accounts.length > 0)
    .map((group) => {
      group.overdue.accounts.sort(compareAccounts)
      group.due_today.accounts.sort(compareAccounts)
      group.overdue.total = round2(group.overdue.total)
      group.due_today.total = round2(group.due_today.total)
      group.total_overdue = round2(group.total_overdue)
      group.total_paid_today = round2(group.total_paid_today)
      return group
    })
    .sort((a, b) => a.cost_center.localeCompare(b.cost_center, 'pt-BR'))

  const paidRows = groups.map((group) => ({ cost_center: group.cost_center, amount: group.total_paid_today }))
  const overdueRows = groups.map((group) => ({ cost_center: group.cost_center, amount: group.total_overdue }))
  const totalPaidToday = round2(paidRows.reduce((sum, row) => sum + row.amount, 0))
  const totalOverdue = round2(overdueRows.reduce((sum, row) => sum + row.amount, 0))

  return {
    reference_date: data.reference_date,
    from: data.from,
    to: data.to,
    groups,
    summary: {
      reference_date: data.reference_date,
      paid_today: { title: 'PAGOS', rows: paidRows, total: totalPaidToday },
      overdue: { title: 'EM ATRASO', rows: overdueRows, total: totalOverdue },
    },
    total_overdue: totalOverdue,
    total_paid_today: totalPaidToday,
  }
}

function compareAccounts(a: PayableAccount, b: PayableAccount): number {
  const dateCompare = a.due_date.localeCompare(b.due_date)
  return dateCompare !== 0 ? dateCompare : a.description.localeCompare(b.description, 'pt-BR')
}

function round2(value: number): number {
  return Math.round(value * 100) / 100
}

function accountRow(account: PayableAccount): string {
  return `<tr>
    <td>${escapeHtml(formatShortDate(account.due_date))}</td>
    <td>${escapeHtml(account.description)}</td>
    <td class="amount">${escapeHtml(formatCurrency(account.remaining_amount))}</td>
  </tr>`
}

export function buildPayablesReportHtml(data: PayablesExportReport, title: string, subtitle: string): string {
  const referenceDate = formatShortDate(data.reference_date)
  const sections: string[] = []

  for (const group of data.groups) {
    sections.push(`<tr class="section-banner"><td colspan="3">${escapeHtml(group.cost_center)}</td></tr>`)
    sections.push(
      `<tr class="column-header">
        <th>Data</th>
        <th>Descrição</th>
        <th class="amount">Valor</th>
      </tr>`,
    )

    if (group.overdue.accounts.length > 0) {
      sections.push(`<tr class="status-overdue"><td colspan="3">EM ATRASO</td></tr>`)
      for (const account of group.overdue.accounts) {
        sections.push(accountRow(account))
      }
      sections.push(
        `<tr class="total-overdue"><td colspan="2">TOTAL EM ATRASO</td><td class="amount">${escapeHtml(formatCurrency(group.overdue.total))}</td></tr>`,
      )
    }

    if (group.due_today.accounts.length > 0) {
      sections.push(
        `<tr class="status-paid"><td colspan="3">PAGOS EM ${escapeHtml(referenceDate)}</td></tr>`,
      )
      for (const account of group.due_today.accounts) {
        sections.push(accountRow(account))
      }
      sections.push(
        `<tr class="total-paid"><td colspan="2">TOTAL PAGO</td><td class="amount">${escapeHtml(formatCurrency(group.due_today.total))}</td></tr>`,
      )
    }

    sections.push('<tr class="spacer"><td colspan="3"></td></tr>')
  }

  const paidSummaryRows = data.summary.paid_today.rows
    .map(
      (row) =>
        `<tr><td>${escapeHtml(row.cost_center)}</td><td class="amount">${escapeHtml(formatCurrency(row.amount))}</td></tr>`,
    )
    .join('')

  const overdueSummaryRows = data.summary.overdue.rows
    .map(
      (row) =>
        `<tr><td>${escapeHtml(row.cost_center)}</td><td class="amount">${escapeHtml(formatCurrency(row.amount))}</td></tr>`,
    )
    .join('')

  const subtitleLines = subtitle
    .split(' · ')
    .map((line) => line.trim())
    .filter(Boolean)

  return `
    <h1 class="report-title">${escapeHtml(title)}</h1>
    ${subtitleLines.map((line) => `<p class="report-subtitle">${escapeHtml(line)}</p>`).join('')}
    <table class="report-table">
      <tbody>${sections.join('')}</tbody>
    </table>
    <h2 class="summary-heading">Resumo geral em ${escapeHtml(referenceDate)}</h2>
    <div class="summary-grid">
      <div class="summary-box">
        <div class="summary-title is-success">${escapeHtml(data.summary.paid_today.title)}</div>
        <table class="report-table" style="margin:0">
          <thead>
            <tr class="column-header"><th>Centro de custo</th><th class="amount">Valor</th></tr>
          </thead>
          <tbody>
            ${paidSummaryRows}
            <tr class="total-paid"><td>TOTAL PAGOS</td><td class="amount">${escapeHtml(formatCurrency(data.summary.paid_today.total))}</td></tr>
          </tbody>
        </table>
      </div>
      <div class="summary-box">
        <div class="summary-title is-danger">Em atraso</div>
        <table class="report-table" style="margin:0">
          <thead>
            <tr class="column-header"><th>Centro de custo</th><th class="amount">Valor</th></tr>
          </thead>
          <tbody>
            ${overdueSummaryRows}
            <tr class="total-overdue"><td>TOTAL EM ATRASO</td><td class="amount">${escapeHtml(formatCurrency(data.summary.overdue.total))}</td></tr>
          </tbody>
        </table>
      </div>
    </div>
  `
}
