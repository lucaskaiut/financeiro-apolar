import { useEffect, useRef, useState } from 'react'
import { useNavigate, useParams, useSearchParams } from 'react-router'
import { CreditCard, FileUp, Save, Upload } from 'lucide-react'
import {
  Badge,
  Button,
  ButtonLink,
  Card,
  CardContent,
  ConfirmDialog,
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
import {
  useCreditCardQuery,
  useDeleteInvoiceImportDraft,
  useImportInvoice,
  useInvoiceImportDrafts,
  usePreviewInvoice,
  useSaveInvoiceImportDraft,
} from '../hooks/useCreditCards'
import { creditCardsService } from '../services/credit-cards.service'
import { round, uid, type PurchaseDraft } from '../types/import-draft'
import { buildSaveDraftPayload, mapDraftItems, type InvoiceImportDraft } from '../utils/import-draft-payload'
import { cn } from '@/shared/utils/cn'

type Step = 1 | 2 | 3

const STEPS = ['Arquivo e parâmetros', 'Revisar compras', 'Confirmar']

function applyDraftToState(draft: InvoiceImportDraft) {
  return {
    step: Math.min(Math.max(draft.step, 2), 3) as Step,
    referenceMonth: draft.reference_month,
    paidDate: draft.paid_date ?? '',
    bankAccountId: draft.bank_account_id ?? '',
    categoryId: draft.category_id ?? '',
    costCenterId: draft.cost_center_id ?? '',
    dueDate: draft.due_date ?? '',
    items: mapDraftItems(draft.items as unknown[]),
    sourceFilename: draft.source_filename,
  }
}

export default function ImportInvoicePage() {
  const { id } = useParams<{ id: string }>()
  const navigate = useNavigate()
  const [searchParams, setSearchParams] = useSearchParams()

  const [step, setStep] = useState<Step>(1)

  const [file, setFile] = useState<File | null>(null)
  const [sourceFilename, setSourceFilename] = useState<string | null>(null)
  const [referenceMonth, setReferenceMonth] = useState('')
  const [paidDate, setPaidDate] = useState('')
  const [bankAccountId, setBankAccountId] = useState('')
  const [categoryId, setCategoryId] = useState('')
  const [costCenterId, setCostCenterId] = useState('')

  const [dueDate, setDueDate] = useState('')
  const [items, setItems] = useState<PurchaseDraft[]>([])

  const [resumeDraft, setResumeDraft] = useState<InvoiceImportDraft | null>(null)
  const [discardDraftMonth, setDiscardDraftMonth] = useState<string | null>(null)
  const resumePromptHandled = useRef(false)

  const fileRef = useRef<HTMLInputElement>(null)

  const cardQuery = useCreditCardQuery(id)
  const bankAccounts = useBankAccountOptions()
  const draftsQuery = useInvoiceImportDrafts(id)
  const preview = usePreviewInvoice(id ?? '')
  const importInvoice = useImportInvoice(id ?? '')
  const saveDraft = useSaveInvoiceImportDraft(id ?? '')
  const deleteDraft = useDeleteInvoiceImportDraft(id ?? '')

  const toImport = items.filter((item) => item.status !== 'ignored')
  const totalValue = round(toImport.reduce((sum, item) => sum + item.value, 0))
  const totalLancamentos = toImport.reduce((sum, item) => sum + (item.splits.length > 0 ? item.splits.length : 1), 0)
  const ignoredCount = items.length - toImport.length
  const rateadaCount = toImport.filter((item) => item.splits.length > 0).length

  const classifiedCount = toImport.filter((item) => {
    if (item.splits.length > 0) {
      return item.splits.every((part) => part.description && part.category_id && part.value > 0)
    }

    return Boolean(item.category_id)
  }).length

  const loadDraft = (draft: InvoiceImportDraft) => {
    const next = applyDraftToState(draft)
    setStep(next.step)
    setReferenceMonth(next.referenceMonth)
    setPaidDate(next.paidDate)
    setBankAccountId(next.bankAccountId)
    setCategoryId(next.categoryId)
    setCostCenterId(next.costCenterId)
    setDueDate(next.dueDate)
    setItems(next.items)
    setSourceFilename(next.sourceFilename)
    setFile(null)
    setSearchParams({ month: draft.reference_month }, { replace: true })
  }

  const fetchDraft = async (month: string): Promise<InvoiceImportDraft | null> => {
    if (!id) return null

    try {
      return await creditCardsService.getInvoiceImportDraft(id, month)
    } catch {
      toast.error('Rascunho', 'Não foi possível carregar o progresso salvo.')

      return null
    }
  }

  useEffect(() => {
    if (!id || draftsQuery.isPending || resumePromptHandled.current || items.length > 0) return

    const monthParam = searchParams.get('month')
    const drafts = draftsQuery.data ?? []

    if (monthParam && drafts.some((entry) => entry.reference_month === monthParam)) {
      resumePromptHandled.current = true
      void fetchDraft(monthParam).then((draft) => {
        if (draft) loadDraft(draft)
      })

      return
    }

    if (!monthParam && drafts.length === 1 && step === 1) {
      resumePromptHandled.current = true
      void fetchDraft(drafts[0].reference_month).then((draft) => {
        if (draft) setResumeDraft(draft)
      })
    }
  }, [draftsQuery.data, draftsQuery.isPending, id, items.length, searchParams, step])

  const persistProgress = async (nextStep: Step = step, options?: { silent?: boolean }) => {
    if (!referenceMonth || items.length === 0) {
      toast.error('Salvar progresso', 'Processe o arquivo ou retome um rascunho antes de salvar.')
      return
    }

    const payload = buildSaveDraftPayload({
      referenceMonth,
      paidDate,
      bankAccountId,
      categoryId,
      costCenterId,
      dueDate,
      step: nextStep,
      sourceFilename: file?.name ?? sourceFilename,
      items,
    })

    try {
      if (options?.silent) {
        await saveDraft.mutateAsync(payload)
      } else {
        await saveDraft.mutateAsync(payload)
      }
    } catch (error) {
      if (!options?.silent) {
        toast.error('Salvar progresso', isApiError(error) ? error.message : 'Não foi possível salvar.')
      }
    }
  }

  const runPreview = async () => {
    if (!file || !referenceMonth || !paidDate || !bankAccountId || !categoryId) {
      toast.error('Importação', 'Selecione o arquivo, o mês de referência, a data do pagamento, a conta bancária e a categoria.')
      return
    }

    const existingDraft = (draftsQuery.data ?? []).find((draft) => draft.reference_month === referenceMonth)
    if (existingDraft) {
      setDiscardDraftMonth(referenceMonth)
      return
    }

    await executePreview()
  }

  const executePreview = async () => {
    if (!file || !referenceMonth || !categoryId) return

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

      const nextItems = result.items.map((item) => ({
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
      }))

      setDueDate(result.due_date)
      setItems(nextItems)
      setSourceFilename(file.name)
      setStep(2)
      setSearchParams({ month: referenceMonth }, { replace: true })

      await saveDraft.mutateAsync(
        buildSaveDraftPayload({
          referenceMonth,
          paidDate,
          bankAccountId,
          categoryId,
          costCenterId,
          dueDate: result.due_date,
          step: 2,
          sourceFilename: file.name,
          items: nextItems,
        }),
      )
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

  const drafts = draftsQuery.data ?? []

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
        actions={
          items.length > 0 ? (
            <Button variant="secondary" onClick={() => persistProgress()} loading={saveDraft.isPending}>
              <Save className="size-4" />
              Salvar progresso
            </Button>
          ) : undefined
        }
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

          {cardQuery.data && drafts.length > 0 && step === 1 && items.length === 0 && (
            <Card className="border border-primary/30 bg-primary/5">
              <CardContent className="space-y-3 p-5">
                <h2 className="text-sm font-semibold text-foreground">Importações em andamento</h2>
                <p className="text-[13px] text-muted">
                  Há classificações salvas que você pode retomar sem precisar enviar o arquivo novamente.
                </p>
                <ul className="space-y-2">
                  {drafts.map((draft) => (
                    <li
                      key={draft.id}
                      className="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-surface-2 bg-surface-1 px-4 py-3"
                    >
                      <div>
                        <p className="font-medium text-foreground">Referência {draft.reference_month}</p>
                        <p className="text-[13px] text-muted">
                          {draft.progress.classified} de {draft.progress.total - draft.progress.ignored} classificadas
                          {draft.source_filename ? ` · ${draft.source_filename}` : ''}
                        </p>
                      </div>
                      <div className="flex flex-wrap gap-2">
                        <Button
                          size="sm"
                          onClick={() => {
                            void fetchDraft(draft.reference_month).then((detail) => {
                              if (detail) loadDraft(detail)
                            })
                          }}
                        >
                          Continuar
                        </Button>
                        <Button
                          size="sm"
                          variant="ghost"
                          className="text-danger hover:bg-danger-soft hover:text-danger"
                          onClick={() => setDiscardDraftMonth(draft.reference_month)}
                        >
                          Descartar
                        </Button>
                      </div>
                    </li>
                  ))}
                </ul>
              </CardContent>
            </Card>
          )}

          {cardQuery.data && (
            <>
              <div className="flex flex-wrap items-center gap-2">
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

              {items.length > 0 && (
                <div className="flex flex-wrap items-center gap-2">
                  <Badge variant="primary">
                    Classificadas {classifiedCount}/{toImport.length}
                  </Badge>
                  {sourceFilename && <Badge variant="neutral">{sourceFilename}</Badge>}
                </div>
              )}

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
                  <div className="flex flex-wrap justify-between gap-3">
                    <Button variant="secondary" onClick={() => setStep(1)}>
                      Voltar
                    </Button>
                    <div className="flex flex-wrap gap-2">
                      <Button variant="secondary" onClick={() => persistProgress(2)} loading={saveDraft.isPending}>
                        <Save className="size-4" />
                        Salvar progresso
                      </Button>
                      <Button
                        onClick={async () => {
                          await persistProgress(3, { silent: true })
                          setStep(3)
                        }}
                        disabled={toImport.length === 0}
                      >
                        Continuar
                      </Button>
                    </div>
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

                  <div className="flex flex-wrap justify-between gap-3">
                    <Button variant="secondary" onClick={() => setStep(2)}>
                      Voltar
                    </Button>
                    <div className="flex flex-wrap gap-2">
                      <Button variant="secondary" onClick={() => persistProgress(3)} loading={saveDraft.isPending}>
                        <Save className="size-4" />
                        Salvar progresso
                      </Button>
                      <Button onClick={confirmImport} loading={importInvoice.isPending}>
                        <Upload className="size-4" />
                        Confirmar importação
                      </Button>
                    </div>
                  </div>
                </>
              )}
            </>
          )}
        </div>
      </PageContent>

      <ConfirmDialog
        open={resumeDraft !== null}
        onClose={() => setResumeDraft(null)}
        onConfirm={() => {
          if (resumeDraft) loadDraft(resumeDraft)
          setResumeDraft(null)
        }}
        title="Continuar importação"
        description={
          <>
            Existe um progresso salvo para <strong>{resumeDraft?.reference_month}</strong> (
            {resumeDraft?.progress.classified} de {resumeDraft ? resumeDraft.progress.total - resumeDraft.progress.ignored : 0}{' '}
            classificadas). Deseja continuar de onde parou?
          </>
        }
        confirmLabel="Continuar"
      />

      <ConfirmDialog
        open={discardDraftMonth !== null}
        onClose={() => setDiscardDraftMonth(null)}
        onConfirm={async () => {
          if (!discardDraftMonth) return
          const month = discardDraftMonth
          const shouldRunPreview = month === referenceMonth && file

          try {
            await deleteDraft.mutateAsync(month)
          } finally {
            setDiscardDraftMonth(null)
            if (shouldRunPreview) await executePreview()
          }
        }}
        loading={deleteDraft.isPending || preview.isPending}
        title="Substituir rascunho"
        description={
          <>
            Já há um progresso salvo para <strong>{discardDraftMonth}</strong>. Descartar o rascunho
            {discardDraftMonth === referenceMonth && file ? ' e processar o novo arquivo' : ''}?
          </>
        }
        confirmLabel="Descartar e continuar"
      />
    </Page>
  )
}
