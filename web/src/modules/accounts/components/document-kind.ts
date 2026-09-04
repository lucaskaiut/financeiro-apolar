import { File, FileSpreadsheet, FileText, type LucideIcon } from 'lucide-react'

export type FileKind = 'image' | 'pdf' | 'spreadsheet' | 'text' | 'other'

export function fileKind(mime: string | null | undefined): FileKind {
  const type = mime ?? ''

  if (type.startsWith('image/')) return 'image'
  if (type === 'application/pdf') return 'pdf'
  if (type.includes('spreadsheet') || type.includes('excel') || type.endsWith('/csv')) return 'spreadsheet'
  if (type.startsWith('text/') || type.includes('word') || type.includes('document')) return 'text'

  return 'other'
}

export const FILE_KIND_ICON: Record<Exclude<FileKind, 'image'>, LucideIcon> = {
  pdf: FileText,
  spreadsheet: FileSpreadsheet,
  text: FileText,
  other: File,
}

export function kindLabel(kind: FileKind): string {
  if (kind === 'pdf') return 'PDF'

  return kind.toUpperCase()
}
