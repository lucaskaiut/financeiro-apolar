import { useRef, useState, type ReactNode } from 'react'
import { Copy, Eye } from 'lucide-react'
import { Button, Modal } from '@/shared/design-system'
import { captureElementToClipboard } from '@/shared/utils/capture-screenshot'
import { toast } from '@/shared/stores/toast.store'
import { cn } from '@/shared/utils/cn'

export interface ScreenReportColumn<T> {
  key: string
  header: string
  align?: 'left' | 'right' | 'center'
  className?: string
  cell: (row: T) => ReactNode
}

export interface ScreenReportSection<T> {
  title?: string
  titleClassName?: string
  rows: T[]
  footer?: { label: string; value: ReactNode }
}

interface ReportScreenViewerProps<T> {
  open: boolean
  onClose: () => void
  title: string
  description?: string
  columns: ScreenReportColumn<T>[]
  rows?: T[]
  sections?: ScreenReportSection<T>[]
  summary?: Array<{ label: string; value: ReactNode; className?: string }>
  rowKey: (row: T, index: number) => string
}

const alignClass = {
  left: 'text-left',
  right: 'text-right',
  center: 'text-center',
} as const

export function ReportViewButton({ onClick, disabled }: { onClick: () => void; disabled?: boolean }) {
  return (
    <Button variant="secondary" onClick={onClick} disabled={disabled}>
      <Eye className="size-4" />
      Visualizar em tela
    </Button>
  )
}

export function ReportScreenViewer<T>({
  open,
  onClose,
  title,
  description,
  columns,
  rows,
  sections,
  summary,
  rowKey,
}: ReportScreenViewerProps<T>) {
  const captureRef = useRef<HTMLDivElement>(null)
  const [copying, setCopying] = useState(false)

  const sectionList = sections ?? (rows ? [{ rows }] : [])

  const copyScreenshot = async () => {
    if (!captureRef.current) return

    setCopying(true)

    try {
      await captureElementToClipboard(captureRef.current)
      toast.success('Print copiado', 'A imagem do relatório foi copiada para a área de transferência.')
    } catch {
      toast.error('Falha ao copiar', 'Não foi possível copiar o print. Tente usar a captura de tela do sistema.')
    } finally {
      setCopying(false)
    }
  }

  const renderRows = (items: T[], sectionIndex: number) =>
    items.map((row, index) => (
      <tr key={`${sectionIndex}-${rowKey(row, index)}`} className="border-b border-gray-200 last:border-b-0">
        {columns.map((column) => (
          <td
            key={column.key}
            className={cn(
              'whitespace-nowrap px-1.5 py-0.5 align-middle text-gray-900',
              alignClass[column.align ?? 'left'],
              column.className,
            )}
          >
            {column.cell(row)}
          </td>
        ))}
      </tr>
    ))

  return (
    <Modal
      open={open}
      onClose={onClose}
      title={title}
      description={description}
      size="xl"
      footer={
        <>
          <Button variant="secondary" onClick={onClose}>
            Fechar
          </Button>
          <Button onClick={copyScreenshot} loading={copying}>
            <Copy className="size-4" />
            Copiar print
          </Button>
        </>
      }
    >
      <div ref={captureRef} className="rounded-lg border border-gray-200 bg-white p-3 text-gray-900">
        <div className="mb-2 border-b border-gray-300 pb-2">
          <h3 className="text-sm font-bold text-gray-900">{title}</h3>
          {description && <p className="text-[11px] text-gray-600">{description}</p>}
        </div>

        <div className="overflow-x-auto">
          <table className="w-max min-w-full border-collapse text-[11px] leading-tight">
            <thead>
              <tr className="border-b border-gray-300 bg-gray-100">
                {columns.map((column) => (
                  <th
                    key={column.key}
                    className={cn(
                      'whitespace-nowrap px-1.5 py-1 text-[10px] font-semibold tracking-wide text-gray-600 uppercase',
                      alignClass[column.align ?? 'left'],
                    )}
                  >
                    {column.header}
                  </th>
                ))}
              </tr>
            </thead>
            <tbody>
              {sectionList.map((section, sectionIndex) => (
                <SectionRows
                  key={section.title ?? sectionIndex}
                  section={section}
                  sectionIndex={sectionIndex}
                  columns={columns}
                  renderRows={renderRows}
                />
              ))}
            </tbody>
          </table>
        </div>

        {summary && summary.length > 0 && (
          <div className="mt-3 border-t border-gray-300 pt-2 text-[11px]">
            {summary.map((item) => (
              <div key={item.label} className={cn('flex items-center justify-between gap-4 py-0.5', item.className)}>
                <span className="text-gray-600">{item.label}</span>
                <span className="whitespace-nowrap font-semibold text-gray-900">{item.value}</span>
              </div>
            ))}
          </div>
        )}
      </div>
    </Modal>
  )
}

function SectionRows<T>({
  section,
  sectionIndex,
  columns,
  renderRows,
}: {
  section: ScreenReportSection<T>
  sectionIndex: number
  columns: ScreenReportColumn<T>[]
  renderRows: (items: T[], sectionIndex: number) => ReactNode
}) {
  if (section.rows.length === 0 && !section.footer) return null

  return (
    <>
      {section.title && (
        <tr>
          <td
            colSpan={columns.length}
            className={cn('px-1.5 pt-2 pb-0.5 text-[11px] font-semibold text-gray-900', section.titleClassName)}
          >
            {section.title}
          </td>
        </tr>
      )}
      {renderRows(section.rows, sectionIndex)}
      {section.footer && (
        <tr className="border-t border-gray-300 bg-gray-50">
          <td colSpan={Math.max(columns.length - 1, 1)} className="px-1.5 py-0.5 text-[11px] font-semibold text-gray-900">
            {section.footer.label}
          </td>
          <td className="whitespace-nowrap px-1.5 py-0.5 text-right text-[11px] font-semibold text-gray-900">
            {section.footer.value}
          </td>
        </tr>
      )}
    </>
  )
}
