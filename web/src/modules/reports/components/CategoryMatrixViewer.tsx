import { useRef, useState } from 'react'
import { Copy, Eye } from 'lucide-react'
import { Button, Modal } from '@/shared/design-system'
import { captureElementToClipboard } from '@/shared/utils/capture-screenshot'
import { toast } from '@/shared/stores/toast.store'
import type { CategoryMatrix } from '../services/reports.service'
import { CategoryMatrixTable } from './CategoryMatrixTable'
import { formatCategoryAmount } from '../utils/category-format'
import { formatDate } from '@/shared/utils/format'

export function CategoryViewButton({ onClick, disabled }: { onClick: () => void; disabled?: boolean }) {
  return (
    <Button variant="secondary" onClick={onClick} disabled={disabled}>
      <Eye className="size-4" />
      Visualizar em tela
    </Button>
  )
}

interface CategoryMatrixViewerProps {
  open: boolean
  onClose: () => void
  matrix: CategoryMatrix
  from: string
  to: string
  costCenterLabel: string
}

export function CategoryMatrixViewer({ open, onClose, matrix, from, to, costCenterLabel }: CategoryMatrixViewerProps) {
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
      title="Relatório por categoria"
      description={`Período: ${formatDate(from)} até ${formatDate(to)} · ${costCenterLabel}`}
      size="2xl"
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
          <h3 className="text-sm font-bold text-gray-900">Relatório por categoria</h3>
          <p className="text-[11px] text-gray-600">
            Período: {formatDate(from)} até {formatDate(to)} · {costCenterLabel}
          </p>
          <div className="mt-2 text-[11px] text-gray-700">
            <span>Total geral: {formatCategoryAmount(matrix.grand_total.total)}</span>
          </div>
        </div>

        <CategoryMatrixTable matrix={matrix} compact />
      </div>
    </Modal>
  )
}
