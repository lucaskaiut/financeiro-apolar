import { useRef, useState } from 'react'
import { FileUp, Upload } from 'lucide-react'
import { Button, Modal, Select } from '@/shared/design-system'
import { useBankAccountOptions } from '@/modules/bank-accounts/hooks/useBankAccounts'
import { CategoryFilterSelect } from '@/modules/categories/components/CategorySearchSelect'
import { useCostCenterOptions } from '@/modules/cost-centers/hooks/useCostCenters'
import { useImportInvoice } from '../hooks/useCreditCards'
import { toast } from '@/shared/stores/toast.store'

export function ImportInvoiceDialog({
  cardId,
  open,
  onClose,
}: {
  cardId: string
  open: boolean
  onClose: () => void
}) {
  const [file, setFile] = useState<File | null>(null)
  const [referenceMonth, setReferenceMonth] = useState('')
  const [paidDate, setPaidDate] = useState('')
  const [bankAccountId, setBankAccountId] = useState('')
  const [categoryId, setCategoryId] = useState('')
  const [costCenterId, setCostCenterId] = useState('')
  const fileRef = useRef<HTMLInputElement>(null)

  const bankAccounts = useBankAccountOptions()
  const costCenters = useCostCenterOptions()
  const importInvoice = useImportInvoice(cardId)

  const reset = () => {
    setFile(null)
    setReferenceMonth('')
    setPaidDate('')
    setBankAccountId('')
    setCategoryId('')
    setCostCenterId('')
    if (fileRef.current) fileRef.current.value = ''
  }

  const handleImport = () => {
    if (!file || !referenceMonth || !paidDate || !bankAccountId || !categoryId) {
      toast.error('Importação', 'Selecione o arquivo, o mês de referência, a data do pagamento, a conta bancária e a categoria.')
      return
    }

    importInvoice.mutate(
      {
        file,
        reference_month: referenceMonth,
        paid_date: paidDate,
        bank_account_id: bankAccountId,
        category_id: categoryId,
        cost_center_id: costCenterId || null,
      },
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
      title="Importar fatura"
      description="Importe a fatura do cartão a partir do extrato (XLSX/XLS). As compras entram com a categoria e o centro de custo selecionados; a fatura é fechada e a conta a pagar é baixada automaticamente."
      size="lg"
      footer={
        <>
          <Button variant="secondary" onClick={onClose}>
            Cancelar
          </Button>
          <Button onClick={handleImport} loading={importInvoice.isPending}>
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
            id="import-invoice-file"
          />
          <label
            htmlFor="import-invoice-file"
            className="flex h-10 cursor-pointer items-center gap-2 rounded-lg bg-surface-2 px-3.5 text-sm text-muted transition-colors hover:bg-surface-3"
          >
            <FileUp className="size-4" />
            {file ? file.name : 'Selecionar arquivo XLSX/XLS'}
          </label>
          <p className="mt-1.5 text-[13px] text-muted">
            Compras já lançadas (mesma data e valor) são ignoradas. Se a fatura deste mês já existir, a importação é
            bloqueada.
          </p>
        </div>

        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
          <div>
            <label className="mb-1.5 block text-[13px] font-medium text-foreground">Mês de referência</label>
            <input
              type="month"
              value={referenceMonth}
              onChange={(e) => setReferenceMonth(e.target.value)}
              className="h-10 w-full rounded-lg border border-surface-3 bg-surface-1 px-3 text-sm text-foreground"
            />
          </div>

          <div>
            <label className="mb-1.5 block text-[13px] font-medium text-foreground">Data do pagamento</label>
            <input
              type="date"
              value={paidDate}
              onChange={(e) => setPaidDate(e.target.value)}
              className="h-10 w-full rounded-lg border border-surface-3 bg-surface-1 px-3 text-sm text-foreground"
            />
          </div>
        </div>

        <div>
          <label className="mb-1.5 block text-[13px] font-medium text-foreground">Conta bancária (pagamento)</label>
          <Select
            aria-label="Conta bancária do pagamento"
            value={bankAccountId}
            onChange={(e) => setBankAccountId(e.target.value)}
            options={bankAccounts.data ?? []}
            placeholder="Selecione a conta bancária"
          />
        </div>

        <div>
          <label className="mb-1.5 block text-[13px] font-medium text-foreground">Categoria padrão</label>
          <CategoryFilterSelect value={categoryId} onChange={setCategoryId} type="expense" className="w-full" />
        </div>

        <div>
          <label className="mb-1.5 block text-[13px] font-medium text-foreground">Centro de custo padrão</label>
          <Select
            aria-label="Centro de custo padrão"
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
