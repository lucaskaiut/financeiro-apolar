import * as XLSX from 'xlsx'
import { downloadBlob } from '@/shared/utils/report-export'

export type ClientXlsxCell = string | number | null | undefined

export interface ClientXlsxTable {
  banner?: string
  headers: string[]
  rows: ClientXlsxCell[][]
  footer?: ClientXlsxCell[]
}

export interface ClientXlsxExportOptions {
  filename: string
  sheetName?: string
  title: string
  subtitleLines?: string[]
  tables: ClientXlsxTable[]
  summary?: Array<{ label: string; value: ClientXlsxCell }>
}

function pushRow(rows: ClientXlsxCell[][], cells: ClientXlsxCell[]) {
  rows.push(cells.map((cell) => (cell === undefined ? null : cell)))
}

/** Builds an .xlsx from the same filtered dataset shown on screen / PDF. */
export function downloadReportXlsx(options: ClientXlsxExportOptions): void {
  const aoa: ClientXlsxCell[][] = []

  pushRow(aoa, [options.title])
  for (const line of options.subtitleLines ?? []) {
    pushRow(aoa, [line])
  }
  pushRow(aoa, [])

  for (const table of options.tables) {
    if (table.banner) {
      pushRow(aoa, [table.banner])
    }
    pushRow(aoa, table.headers)
    for (const row of table.rows) {
      pushRow(aoa, row)
    }
    if (table.footer) {
      pushRow(aoa, table.footer)
    }
    pushRow(aoa, [])
  }

  if (options.summary && options.summary.length > 0) {
    for (const item of options.summary) {
      pushRow(aoa, [item.label, item.value])
    }
  }

  const worksheet = XLSX.utils.aoa_to_sheet(aoa)
  const workbook = XLSX.utils.book_new()
  XLSX.utils.book_append_sheet(workbook, worksheet, options.sheetName ?? 'Relatório')

  const buffer = XLSX.write(workbook, { bookType: 'xlsx', type: 'array' })
  downloadBlob(
    new Blob([buffer], {
      type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    }),
    options.filename,
  )
}
