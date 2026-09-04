import { REPORT_PRINT_STYLES } from '@/modules/reports/utils/report-print-styles'

export function escapeHtml(value: unknown): string {
  return String(value ?? '').replace(/[&<>"]/g, (char) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' })[char] ?? char)
}

export function downloadBlob(blob: Blob, filename: string): void {
  const url = URL.createObjectURL(blob)
  const anchor = document.createElement('a')
  anchor.href = url
  anchor.download = filename
  anchor.click()
  URL.revokeObjectURL(url)
}

export function wrapReportPrintDocument(title: string, bodyHtml: string): string {
  return `<!doctype html>
<html lang="pt-BR">
  <head>
    <meta charset="utf-8" />
    <title>${escapeHtml(title)}</title>
    <style>${REPORT_PRINT_STYLES}</style>
  </head>
  <body>
    ${bodyHtml}
  </body>
</html>`
}

export function printHtmlReport(title: string, content: string): void {
  const bodyHtml = looksLikeFullDocument(content) ? extractBodyHtml(content) : content
  const html = wrapReportPrintDocument(title, bodyHtml)

  const win = window.open('', '_blank', 'width=900,height=700')

  if (!win) {
    return
  }

  win.document.open()
  win.document.write(html)
  win.document.close()
  win.focus()
  win.addEventListener('afterprint', () => win.close())
  win.print()
}

function looksLikeFullDocument(content: string): boolean {
  return /<!doctype html|<html[\s>]/i.test(content)
}

function extractBodyHtml(content: string): string {
  const match = content.match(/<body[^>]*>([\s\S]*?)<\/body>/i)
  return match?.[1]?.trim() || content
}
