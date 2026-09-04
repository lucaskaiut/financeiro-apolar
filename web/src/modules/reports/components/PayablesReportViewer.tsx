import { useRef, useState } from 'react'
import { Copy, Eye } from 'lucide-react'
import { Button, Modal } from '@/shared/design-system'
import { captureElementToClipboard } from '@/shared/utils/capture-screenshot'
import { toast } from '@/shared/stores/toast.store'
import { formatCurrency, formatShortDate } from '@/shared/utils/format'
import type { PayablesExportReport } from '../services/reports.service'
import { PayablesReportLayout } from './PayablesReportLayout'

export function PayablesViewButton({ onClick, disabled }: { onClick: () => void; disabled?: boolean }) {
  return (
    <Button variant="secondary" onClick={onClick} disabled={disabled}>
      <Eye className="size-4" />
      Visualizar em tela
    </Button>
  )
}

interface PayablesReportViewerProps {
  open: boolean
  onClose: () => void
  data: PayablesExportReport
  costCenterLabel: string
}

export function PayablesReportViewer({ open, onClose, data, costCenterLabel }: PayablesReportViewerProps) {
  const captureRef = useRef<HTMLDivElement>(null)
  const [copying, setCopying] = useState(false)

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

  const subtitle = `Referência: ${formatShortDate(data.reference_date)} · Período: ${formatShortDate(data.from)} até ${formatShortDate(data.to)} · ${costCenterLabel}`

  return (
    <Modal
      open={open}
      onClose={onClose}
      title="Relatório de contas a pagar"
      description={subtitle}
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
      <div ref={captureRef} className="rounded-lg border border-gray-200 bg-white p-4 text-gray-900">
        <div className="mb-4 border-b border-gray-300 pb-3">
          <h3 className="text-sm font-bold text-gray-900">Relatório de contas a pagar</h3>
          <p className="text-[11px] text-gray-600">{subtitle}</p>
          <div className="mt-2 flex flex-wrap gap-4 text-[11px]">
            <span className="font-semibold text-red-600">Total em atraso: {formatCurrency(data.total_overdue)}</span>
            <span className="font-semibold text-green-700">Total pago hoje: {formatCurrency(data.total_paid_today)}</span>
          </div>
        </div>

        <PayablesReportLayout data={data} compact />
      </div>
    </Modal>
  )
}
