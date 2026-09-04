import { useRef, useState } from 'react'
import { FileUp, Upload } from 'lucide-react'
import { Button, Modal, Select } from '@/shared/design-system'
import { useBankAccountOptions } from '@/modules/bank-accounts/hooks/useBankAccounts'
import { useImportAccounts } from '../hooks/useAccounts'
import { toast } from '@/shared/stores/toast.store'

export function ImportAccountsDialog({ open, onClose }: { open: boolean; onClose: () => void }) {
  const [file, setFile] = useState<File | null>(null)
  const [bankAccountId, setBankAccountId] = useState('')
  const fileRef = useRef<HTMLInputElement>(null)

  const bankAccounts = useBankAccountOptions()
  const importAccounts = useImportAccounts()

  const reset = () => {
    setFile(null)
    setBankAccountId('')
    if (fileRef.current) fileRef.current.value = ''
  }

  const handleImport = () => {
    if (!file || !bankAccountId) {
      toast.error('Importação', 'Selecione o arquivo e a conta bancária.')
      return
    }

    importAccounts.mutate(
      { file, bankAccountId },
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
      description="Importe despesas a partir de uma planilha XLSX (Data, Histórico, Débito (R$), TIPO, CONSIDERAR e GRUPO)."
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
            A coluna <strong>GRUPO</strong> vira categoria e a coluna <strong>TIPO</strong> vira subcategoria
            (criadas automaticamente). Só linhas com "CONSIDERAR" vazio ou "SIM" são importadas como contas a pagar.
          </p>
        </div>

        <Select
          aria-label="Conta bancária"
          value={bankAccountId}
          onChange={(e) => setBankAccountId(e.target.value)}
          options={bankAccounts.data ?? []}
          placeholder="Selecione a conta bancária"
        />
      </div>
    </Modal>
  )
}
