import { useRef, useState } from 'react'
import { useNavigate, useParams } from 'react-router'
import { CreditCard, FileUp, Upload } from 'lucide-react'
import {
  Badge,
  Button,
  ButtonLink,
  Card,
  CardContent,
  EmptyState,
  Page,
  PageContent,
  PageHeader,
  Select,
  Skeleton,
} from '@/shared/design-system'
import { useBankAccountOptions } from '@/modules/bank-accounts/hooks/useBankAccounts'
import { formatCurrency, formatDate } from '@/shared/utils/format'
import { isApiError } from '@/shared/api/errors'
import { toast } from '@/shared/stores/toast.store'
import { CategorySelect, CostCenterSelect } from '../components/ClassificationSelects'
import { PurchaseReviewStep } from '../components/PurchaseReviewStep'
import { useCreditCardQuery, useImportInvoice, usePreviewInvoice } from '../hooks/useCreditCards'
import { round, uid, type PurchaseDraft } from '../types/import-draft'
import { cn } from '@/shared/utils/cn'

type Step = 1 | 2 | 3

const STEPS = ['Arquivo e parâmetros', 'Revisar compras', 'Confirmar']

export default function ImportInvoicePage() {
  const { id } = useParams<{ id: string }>()
  const navigate = useNavigate()

  const [step, setStep] = useState<Step>(1)

  const [file, setFile] = useState<File | null>(null)
  const [referenceMonth, setReferenceMonth] = useState('')
  const [paidDate, setPaidDate] = useState('')
  const [bankAccountId, setBankAccountId] = useState('')
  const [categoryId, setCategoryId] = useState('')
  const [costCenterId, setCostCenterId] = useState('')

  const [dueDate, setDueDate] = useState('')
  const [items, setItems] = useState<PurchaseDraft[]>([])

  const fileRef = useRef<HTMLInputElement>(null)

  const cardQuery = useCreditCardQuery(id)
  const bankAccounts = useBankAccountOptions()
  const preview = usePreviewInvoice(id ?? '')
  const importInvoice = useImportInvoice(id ?? '')

  const toImport = items.filter((item) => item.status !== 'ignored')
  const totalValue = round(toImport.reduce((sum, item) => sum + item.value, 0))
  const totalLancamentos = toImport.reduce((sum, item) => sum + (item.splits.length > 0 ? item.splits.length : 1), 0)
  const ignoredCount = items.length - toImport.length
  const rateadaCount = toImport.filter((item) => item.splits.length > 0).length

  const runPreview = async () => {
    if (!file || !referenceMonth || !paidDate || !bankAccountId || !categoryId) {
      toast.error('Importação', 'Selecione o arquivo, o mês de referência, a data do pagamento, a conta bancária e a categoria.')
      return
    }

    try {
      const result = await preview.mutateAsync({
        file,
        reference_month: referenceMonth,
        category_id: categoryId,
        cost_center_id: costCenterId || null,
      })

      if (result.items.length === 0) {
        toast.error('Importação', 'Nenhuma compra foi identificada no arquivo.')
        return
      }

      setDueDate(result.due_date)
      setItems(
        result.items.map((item) => ({
          id: uid('p'),
          purchase_date: item.purchase_date,
          description: item.description,
          value: item.value,
          category_id: item.category_id ?? '',
          subcategory_id: item.subcategory_id ?? '',
          cost_center_id: item.cost_center_id ?? '',
          status: item.status,
          is_duplicate: item.is_duplicate,
          existing: item.existing,
          splits: [],
        })),
      )
      setStep(2)
    } catch (error) {
      if (isApiError(error)) {
        const fieldError = Object.values(error.fieldErrors ?? {}).flat()[0]
        toast.error('Falha ao processar o arquivo', fieldError || error.message)
      } else {
        toast.error('Falha ao processar o arquivo', 'Não foi possível ler as compras.')
      }
    }
  }

  const validateBeforeConfirm = (): boolean => {
    for (const item of toImport) {
      if (item.splits.length > 0) {
        for (const part of item.splits) {
          if (!part.description || !part.category_id || part.value <= 0) {
            toast.error('Rateio incompleto', `Revise o rateio de "${item.description}".`)
            return false
          }
        }

        const sum = round(item.splits.reduce((acc, part) => acc + part.value, 0))
        if (Math.abs(sum - item.value) >= 0.01) {
          toast.error('Rateio inválido', `A soma das partes de "${item.description}" não é igual ao valor da compra.`)
          return false
        }
      } else if (!item.category_id) {
        toast.error('Classificação incompleta', `Defina a categoria de "${item.description}".`)
        return false
      }
    }

    return true
  }

  const confirmImport = async () => {
    if (!validateBeforeConfirm()) return

    try {
      await importInvoice.mutateAsync({
        reference_month: referenceMonth,
        paid_date: paidDate,
        bank_account_id: bankAccountId,
        items: items.map((item) => {
          const base = {
            description: item.description,
            purchase_date: item.purchase_date,
            value: item.value,
            status: item.status,
          }

          if (item.status === 'ignored') {
            return base
          }

          if (item.splits.length > 0) {
            return {
              ...base,
              splits: item.splits.map((part) => ({
                description: part.description,
                value: part.value,
                category_id: part.category_id,
                subcategory_id: part.subcategory_id || null,
                cost_center_id: part.cost_center_id || null,
              })),
            }
          }

          return {
            ...base,
            category_id: item.category_id,
            subcategory_id: item.subcategory_id || null,
            cost_center_id: item.cost_center_id || null,
          }
        }),
      })

      navigate(`/credit-cards/${id}`)
    } catch (error) {
      if (isApiError(error) && error.status === 422) {
        toast.error('Importação', error.message)
      } else {
        toast.error('Falha na importação', isApiError(error) ? error.message : 'Não foi possível concluir a importação.')
      }
    }
  }

  return (
    <Page>
      <PageHeader
        title="Importar fatura"
        description="Importe a fatura a partir do extrato (XLSX/XLS), revise e classifique cada compra antes de confirmar."
        breadcrumb={[
          { label: 'Dashboard', to: '/dashboard' },
          { label: 'Cartões de crédito', to: '/credit-cards' },
          { label: cardQuery.data?.name ?? 'Detalhes', to: `/credit-cards/${id}` },
          { label: 'Importar fatura' },
        ]}
      />

      <PageContent>
        <div className="mx-auto w-full max-w-[1200px] space-y-6">
          {cardQuery.isPending && (
            <Card>
              <Skeleton className="h-24 w-full" />
            </Card>
          )}

          {cardQuery.isError && (
            <Card>
              <EmptyState icon={CreditCard} title="Cartão não encontrado" action={<ButtonLink to="/credit-cards" variant="secondary">Voltar</ButtonLink>} />
            </Card>
          )}

          {cardQuery.data && (
            <>
              <div className="flex items-center gap-2">
                {STEPS.map((label, index) => {
                  const current = index + 1
                  const active = current === step
                  const done = current < step

                  return (
                    <div key={label} className="flex items-center gap-2">
                      <span
                        className={cn(
                          'flex size-7 items-center justify-center rounded-full text-sm font-medium',
                          active && 'bg-primary text-primary-foreground',
                          done && 'bg-success-soft text-success',
                          !active && !done && 'bg-surface-2 text-muted',
                        )}
                      >
                        {done ? '✓' : current}
                      </span>
                      <span className={cn('text-sm', active ? 'font-medium text-foreground' : 'text-muted')}>{label}</span>
                      {current < STEPS.length && <span className="mx-1 h-px w-6 bg-surface-3 sm:w-12" />}
                    </div>
                  )
                })}
              </div>

              {step === 1 && (
                <Card className="border border-surface-2">
                  <CardContent className="space-y-5 p-6">
                    <div>
                      <span className="mb-1.5 block text-sm font-medium text-foreground">Arquivo</span>
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
                        className="flex h-11 cursor-pointer items-center gap-2 rounded-lg bg-surface-2 px-3.5 text-sm text-muted transition-colors hover:bg-surface-3"
                      >
                        <FileUp className="size-4" />
                        {file ? file.name : 'Selecionar arquivo XLSX/XLS'}
                      </label>
                    </div>

                    <div className="grid gap-5 sm:grid-cols-2">
                      <div>
                        <span className="mb-1.5 block text-sm font-medium text-foreground">Mês de referência</span>
                        <input
                          type="month"
                          value={referenceMonth}
                          onChange={(e) => setReferenceMonth(e.target.value)}
                          className="h-11 w-full rounded-lg bg-surface-2 px-3.5 text-sm text-foreground"
                        />
                      </div>
                      <div>
                        <span className="mb-1.5 block text-sm font-medium text-foreground">Data do pagamento</span>
                        <input
                          type="date"
                          value={paidDate}
                          onChange={(e) => setPaidDate(e.target.value)}
                          className="h-11 w-full rounded-lg bg-surface-2 px-3.5 text-sm text-foreground"
                        />
                      </div>
                      <div>
                        <span className="mb-1.5 block text-sm font-medium text-foreground">Conta bancária (pagamento)</span>
                        <Select
                          aria-label="Conta bancária do pagamento"
                          value={bankAccountId}
                          onChange={(e) => setBankAccountId(e.target.value)}
                          options={bankAccounts.data ?? []}
                          placeholder="Selecione a conta bancária"
                        />
                      </div>
                      <div>
                        <span className="mb-1.5 block text-sm font-medium text-foreground">Categoria padrão</span>
                        <CategorySelect value={categoryId} onChange={setCategoryId} placeholder="Selecione a categoria" />
                      </div>
                      <div>
                        <span className="mb-1.5 block text-sm font-medium text-foreground">Centro de custo padrão</span>
                        <CostCenterSelect value={costCenterId} onChange={setCostCenterId} />
                      </div>
                    </div>

                    <div className="flex justify-end">
                      <Button onClick={runPreview} loading={preview.isPending}>
                        <Upload className="size-4" />
                        Continuar
                      </Button>
                    </div>
                  </CardContent>
                </Card>
              )}

              {step === 2 && (
                <>
                  <PurchaseReviewStep items={items} onChange={setItems} />
                  <div className="flex justify-between">
                    <Button variant="secondary" onClick={() => setStep(1)}>
                      Voltar
                    </Button>
                    <Button onClick={() => setStep(3)} disabled={toImport.length === 0}>
                      Continuar
                    </Button>
                  </div>
                </>
              )}

              {step === 3 && (
                <>
                  <Card className="border border-surface-2">
                    <CardContent className="space-y-4 p-6">
                      <h2 className="text-base font-semibold text-foreground">Resumo da importação</h2>
                      <dl className="grid gap-4 sm:grid-cols-2">
                        <div>
                          <dt className="text-[13px] text-muted">Mês de referência</dt>
                          <dd className="font-medium text-foreground">{referenceMonth}</dd>
                        </div>
                        <div>
                          <dt className="text-[13px] text-muted">Data do pagamento</dt>
                          <dd className="font-medium text-foreground">{formatDate(paidDate)}</dd>
                        </div>
                        <div>
                          <dt className="text-[13px] text-muted">Vencimento da fatura</dt>
                          <dd className="font-medium text-foreground">{formatDate(dueDate)}</dd>
                        </div>
                        <div>
                          <dt className="text-[13px] text-muted">Valor total</dt>
                          <dd className="font-medium text-foreground">{formatCurrency(totalValue)}</dd>
                        </div>
                      </dl>

                      <div className="flex flex-wrap gap-2 border-t border-surface-2 pt-4">
                        <Badge variant="primary">{totalLancamentos} lançamentos</Badge>
                        <Badge variant="warning">{ignoredCount} ignoradas</Badge>
                        <Badge variant="neutral">{rateadaCount} rateadas</Badge>
                      </div>
                    </CardContent>
                  </Card>

                  <div className="flex justify-between">
                    <Button variant="secondary" onClick={() => setStep(2)}>
                      Voltar
                    </Button>
                    <Button onClick={confirmImport} loading={importInvoice.isPending}>
                      <Upload className="size-4" />
                      Confirmar importação
                    </Button>
                  </div>
                </>
              )}
            </>
          )}
        </div>
      </PageContent>
    </Page>
  )
}
