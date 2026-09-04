import { useRef, useState } from 'react'
import { Copy, Eye } from 'lucide-react'
import { Button, Modal } from '@/shared/design-system'
import { captureElementToClipboard } from '@/shared/utils/capture-screenshot'
import { toast } from '@/shared/stores/toast.store'
import type { ProvisionReport } from '../services/reports.service'
import { ProvisionMatrixTable } from './ProvisionMatrixTable'
import { formatCurrency, formatDate } from '@/shared/utils/format'

export function ProvisionViewButton({ onClick, disabled }: { onClick: () => void; disabled?: boolean }) {
  return (
    <Button variant="secondary" onClick={onClick} disabled={disabled}>
      <Eye className="size-4" />
      Visualizar em tela
    </Button>
  )
}

interface ProvisionMatrixViewerProps {
  open: boolean
  onClose: () => void
  data: ProvisionReport
  costCenterLabel: string
}

export function ProvisionMatrixViewer({ open, onClose, data, costCenterLabel }: ProvisionMatrixViewerProps) {
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

  return (
    <Modal
      open={open}
      onClose={onClose}
      title="Relatório de provisão"
      description={`Período: ${formatDate(data.from)} até ${formatDate(data.to)} · ${costCenterLabel}`}
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
        <div className="mb-3 border-b border-gray-300 pb-2">
          <h3 className="text-sm font-bold text-gray-900">Relatório de provisão</h3>
          <p className="text-[11px] text-gray-600">
            Período: {formatDate(data.from)} até {formatDate(data.to)} · {costCenterLabel}
          </p>
          <div className="mt-2 flex flex-wrap gap-4 text-[11px] text-gray-700">
            <span>Total a receber: {formatCurrency(data.total_in)}</span>
            <span>Total a pagar: {formatCurrency(data.total_out)}</span>
            <span>Saldo líquido: {formatCurrency(data.grand_total.total)}</span>
          </div>
        </div>

        <ProvisionMatrixTable data={data} compact />
      </div>
    </Modal>
  )
}
