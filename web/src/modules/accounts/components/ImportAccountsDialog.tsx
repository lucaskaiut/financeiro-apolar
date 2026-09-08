import { useRef, useState } from 'react'
import { FileUp, Upload } from 'lucide-react'
import { Button, Modal, Select } from '@/shared/design-system'
import { useBankAccountOptions } from '@/modules/bank-accounts/hooks/useBankAccounts'
import { useCostCenterOptions } from '@/modules/cost-centers/hooks/useCostCenters'
import { useImportAccounts } from '../hooks/useAccounts'
import { toast } from '@/shared/stores/toast.store'

export function ImportAccountsDialog({ open, onClose }: { open: boolean; onClose: () => void }) {
  const [file, setFile] = useState<File | null>(null)
  const [bankAccountId, setBankAccountId] = useState('')
  const [costCenterId, setCostCenterId] = useState('')
  const fileRef = useRef<HTMLInputElement>(null)

  const bankAccounts = useBankAccountOptions()
  const costCenters = useCostCenterOptions()
  const importAccounts = useImportAccounts()

  const reset = () => {
    setFile(null)
    setBankAccountId('')
    setCostCenterId('')
    if (fileRef.current) fileRef.current.value = ''
  }

  const handleImport = () => {
    if (!file || !bankAccountId || !costCenterId) {
      toast.error('Importação', 'Selecione o arquivo, a conta bancária e o centro de custo.')
      return
    }

    importAccounts.mutate(
      { file, bankAccountId, costCenterId },
      {
        onSuccess: () => {
          reset()
          onClose()
        },
      },
    )
  }

  return (
    <Modal
      open={open}
      onClose={onClose}
      title="Importar planilha"
      description="Importe despesas a partir de uma planilha XLSX. As contas entram no centro de custo selecionado, com categoria e subcategoria criadas automaticamente."
      size="lg"
      footer={
        <>
          <Button variant="secondary" onClick={onClose}>
            Cancelar
          </Button>
          <Button onClick={handleImport} loading={importAccounts.isPending}>
            <Upload className="size-4" />
            Importar
          </Button>
        </>
      }
    >
      <div className="space-y-4">
        <div>
          <label className="mb-1.5 block text-[13px] font-medium text-foreground">Arquivo</label>
          <input
            ref={fileRef}
            type="file"
            accept=".xlsx,.xls"
            onChange={(e) => setFile(e.target.files?.[0] ?? null)}
            className="hidden"
            id="import-file"
          />
          <label
            htmlFor="import-file"
            className="flex h-10 cursor-pointer items-center gap-2 rounded-lg bg-surface-2 px-3.5 text-sm text-muted transition-colors hover:bg-surface-3"
          >
            <FileUp className="size-4" />
            {file ? file.name : 'Selecionar arquivo XLSX'}
          </label>
          <p className="mt-1.5 text-[13px] text-muted">
            Use a aba <strong>BASE DE DADOS</strong>: GRUPO vira categoria, TIPO DE DESPESA vira subcategoria e TIPO DE
            DESPESA 2 (se houver) vira a descrição. Contas com STATUS Pago/Baixada são liquidadas na data de PGTO; se
            essa data estiver vazia, a baixa usa o vencimento. Linhas A Vencer ou Vencido entram em aberto.
          </p>
        </div>

        <div>
          <label className="mb-1.5 block text-[13px] font-medium text-foreground">Conta bancária</label>
          <Select
            aria-label="Conta bancária"
            value={bankAccountId}
            onChange={(e) => setBankAccountId(e.target.value)}
            options={bankAccounts.data ?? []}
            placeholder="Selecione a conta bancária"
          />
        </div>

        <div>
          <label className="mb-1.5 block text-[13px] font-medium text-foreground">Centro de custo</label>
          <Select
            aria-label="Centro de custo"
            value={costCenterId}
            onChange={(e) => setCostCenterId(e.target.value)}
            options={costCenters.data ?? []}
            placeholder="Selecione o centro de custo"
          />
        </div>
      </div>
    </Modal>
  )
}
