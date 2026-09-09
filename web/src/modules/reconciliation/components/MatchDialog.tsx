import { useCallback, useEffect, useMemo, useState } from 'react'
import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { z } from 'zod'
import { Link2, Plus, Search } from 'lucide-react'
import {
  Alert,
  Badge,
  Button,
  Checkbox,
  ConfirmDialog,
  Form,
  Input,
  Modal,
  RadioGroupField,
  SearchSelectField,
  SelectField,
  Skeleton,
  TextField,
  type SearchSelectOption,
} from '@/shared/design-system'
import { formatCurrency, formatDate } from '@/shared/utils/format'
import { toast } from '@/shared/stores/toast.store'
import { CategorySearchSelectField } from '@/modules/categories/components/CategorySearchSelect'
import { costCentersService } from '@/modules/cost-centers/services/cost-centers.service'
import { useBankAccountOptions } from '@/modules/bank-accounts/hooks/useBankAccounts'
import type { Account, BankTransaction } from '@/shared/types/models'
import { useCandidates, useCreateAccountFromTransaction, useReconcileMany } from '../hooks/useReconciliation'

const createSchema = z.object({
  type: z.enum(['payable', 'receivable']),
  description: z.string().min(1, 'Informe a descrição'),
  category_id: z.string().min(1, 'Selecione a categoria'),
  bank_account_id: z.string().min(1, 'Selecione o conta bancária'),
  cost_center_id: z.string(),
  value: z.string().refine((v) => v !== '' && Number(v) > 0, 'Informe um valor válido'),
  due_date: z.string().min(1, 'Informe a data'),
})

type CreateFormValues = z.infer<typeof createSchema>

function roundMoney(value: number): number {
  return Math.round(value * 100) / 100
}

function willChangeBankAccount(account: Account, transaction: BankTransaction): boolean {
  return Boolean(transaction.bank_account_id) && account.bank_account_id !== transaction.bank_account_id
}

export function MatchDialog({
  transaction,
  from,
  to,
  open,
  onClose,
}: {
  transaction: BankTransaction | null
  from: string
  to: string
  open: boolean
  onClose: () => void
}) {
  const candidates = useCandidates(open && transaction ? transaction.id : undefined, from || undefined, to || undefined, false)
  const reconcileMany = useReconcileMany()
  const createAccount = useCreateAccountFromTransaction()
  const bankAccounts = useBankAccountOptions()

  const [creating, setCreating] = useState(false)
  const [search, setSearch] = useState('')
  const [selectedIds, setSelectedIds] = useState<string[]>([])
  const [confirmAction, setConfirmAction] = useState<'reconcile' | 'create' | null>(null)
  const [pendingCreate, setPendingCreate] = useState<CreateFormValues | null>(null)

  const form = useForm<CreateFormValues>({
    resolver: zodResolver(createSchema),
    defaultValues: {
      type: 'payable',
      description: '',
      category_id: '',
      bank_account_id: '',
      cost_center_id: '',
      value: '',
      due_date: '',
    },
  })

  useEffect(() => {
    if (!open) return

    setCreating(false)
    setSearch('')
    setSelectedIds([])
    setConfirmAction(null)
    setPendingCreate(null)
  }, [open, transaction?.id])

  useEffect(() => {
    if (!transaction) return

    form.reset({
      type: transaction.type === 'credit' ? 'receivable' : 'payable',
      description: transaction.description ?? '',
      category_id: '',
      bank_account_id: transaction.bank_account_id ?? '',
      cost_center_id: '',
      value: String(transaction.value ?? ''),
      due_date: transaction.date ?? '',
    })
  }, [transaction, form])

  const type = form.watch('type')
  const categoryType = type === 'receivable' ? 'income' : 'expense'

  const loadCostCenters = useCallback(async (searchTerm: string): Promise<SearchSelectOption[]> => {
    const result = await costCentersService.list({
      search: searchTerm || undefined,
      per_page: 50,
    })

    return result.data
      .filter((costCenter) => costCenter.status === 'active')
      .map((costCenter) => ({ value: costCenter.id, label: costCenter.name }))
  }, [])

  const resolveCostCenterLabel = useCallback(async (id: string): Promise<SearchSelectOption | null> => {
    try {
      const costCenter = await costCentersService.get(id)
      return { value: costCenter.id, label: costCenter.name }
    } catch {
      return null
    }
  }, [])

  const accounts = candidates.data?.candidates ?? []
  const txValue = transaction ? roundMoney(transaction.value) : 0
  const targetBankName = transaction?.bank_account ?? 'conta do extrato'

  const filteredAccounts = useMemo(() => {
    const term = search.trim().toLowerCase()
    const list = term
      ? accounts.filter(
          (account) =>
            account.description.toLowerCase().includes(term) ||
            (account.counterparty?.toLowerCase().includes(term) ?? false) ||
            (account.bank_account?.toLowerCase().includes(term) ?? false),
        )
      : accounts

    return [...list].sort((a, b) => {
      if (transaction) {
        const aSameBank = a.bank_account_id === transaction.bank_account_id ? 0 : 1
        const bSameBank = b.bank_account_id === transaction.bank_account_id ? 0 : 1
        if (aSameBank !== bSameBank) return aSameBank - bSameBank
      }

      const aExact = Math.abs(a.remaining_amount - txValue) < 0.005 ? 0 : 1
      const bExact = Math.abs(b.remaining_amount - txValue) < 0.005 ? 0 : 1
      if (aExact !== bExact) return aExact - bExact

      return (a.due_date ?? '').localeCompare(b.due_date ?? '')
    })
  }, [accounts, search, txValue, transaction])

  const selectedAccounts = useMemo(
    () => accounts.filter((account) => selectedIds.includes(account.id)),
    [accounts, selectedIds],
  )

  const accountsChangingBank = useMemo(
    () => (transaction ? selectedAccounts.filter((account) => willChangeBankAccount(account, transaction)) : []),
    [selectedAccounts, transaction],
  )

  const selectedTotal = roundMoney(selectedAccounts.reduce((sum, account) => sum + account.remaining_amount, 0))
  const difference = roundMoney(txValue - selectedTotal)
  const canConfirm = selectedIds.length > 0 && Math.abs(difference) < 0.01

  const toggleAccount = (accountId: string) => {
    setSelectedIds((current) =>
      current.includes(accountId) ? current.filter((id) => id !== accountId) : [...current, accountId],
    )
  }

  const openCreateForm = () => {
    if (!transaction) return

    if (selectedIds.length > 0 && difference <= 0.009) {
      toast.error('Valor já completo', 'As contas selecionadas já atingem o valor do extrato. Conciliê-as ou remova alguma seleção.')
      return
    }

    const nextValue = selectedIds.length > 0 ? difference : txValue

    form.reset({
      type: transaction.type === 'credit' ? 'receivable' : 'payable',
      description: transaction.description ?? '',
      category_id: '',
      bank_account_id: transaction.bank_account_id ?? '',
      cost_center_id: '',
      value: String(nextValue),
      due_date: transaction.date ?? '',
    })
    setCreating(true)
  }

  const submitReconciliation = async () => {
    if (!transaction || !canConfirm) return

    await reconcileMany.mutateAsync({
      transactions: [transaction.id],
      accounts: selectedIds,
    })
    setConfirmAction(null)
    onClose()
  }

  const submitCreate = async (values: CreateFormValues) => {
    if (!transaction) return

    const createdValue = roundMoney(Number(values.value))

    if (selectedIds.length > 0) {
      const combined = roundMoney(selectedTotal + createdValue)
      if (Math.abs(combined - txValue) >= 0.01) {
        toast.error(
          'Valores não fecham',
          `Selecionado ${formatCurrency(selectedTotal)} + novo ${formatCurrency(createdValue)} = ${formatCurrency(combined)}, mas o extrato é ${formatCurrency(txValue)}.`,
        )
        return
      }
    } else if (Math.abs(createdValue - txValue) >= 0.01) {
      toast.error('Valor inválido', `O lançamento deve ter o valor do extrato (${formatCurrency(txValue)}).`)
      return
    }

    await createAccount.mutateAsync({
      id: transaction.id,
      payload: {
        type: values.type,
        description: values.description,
        category_id: values.category_id,
        bank_account_id: transaction.bank_account_id || values.bank_account_id || undefined,
        cost_center_id: values.cost_center_id || null,
        value: createdValue,
        due_date: values.due_date,
        account_ids: selectedIds.length > 0 ? selectedIds : undefined,
      },
    })
    setConfirmAction(null)
    setPendingCreate(null)
    onClose()
  }

  const handleConfirm = async () => {
    if (!transaction || !canConfirm) return

    if (accountsChangingBank.length > 0) {
      setConfirmAction('reconcile')
      return
    }

    await submitReconciliation()
  }

  const handleCreate = async (values: CreateFormValues) => {
    if (!transaction) return

    if (selectedIds.length > 0 && accountsChangingBank.length > 0) {
      setPendingCreate(values)
      setConfirmAction('create')
      return
    }

    await submitCreate(values)
  }

  const handleConfirmDialog = async () => {
    if (confirmAction === 'reconcile') {
      await submitReconciliation()
      return
    }

    if (confirmAction === 'create' && pendingCreate) {
      await submitCreate(pendingCreate)
    }
  }

  const renderAccountRow = (account: Account) => {
    const exact = Math.abs(account.remaining_amount - txValue) < 0.005
    const checked = selectedIds.includes(account.id)
    const bankMismatch = transaction ? willChangeBankAccount(account, transaction) : false

    return (
      <div
        key={account.id}
        role="checkbox"
        aria-checked={checked}
        aria-label={`Selecionar ${account.description}`}
        tabIndex={0}
        onClick={() => toggleAccount(account.id)}
        onKeyDown={(event) => {
          if (event.key === 'Enter' || event.key === ' ') {
            event.preventDefault()
            toggleAccount(account.id)
          }
        }}
        className="flex cursor-pointer items-center gap-3 rounded-lg bg-surface-2 p-3 transition-colors hover:bg-surface-3"
      >
        <Checkbox
          id={`reconcile-account-${account.id}`}
          checked={checked}
          readOnly
          tabIndex={-1}
          className="pointer-events-none"
        />
        <div className="min-w-0 flex-1">
          <p className="truncate text-sm font-medium text-foreground">{account.description}</p>
          <p className="truncate text-[13px] text-muted">
            {account.bank_account ?? 'Sem conta bancária'} · vencimento {formatDate(account.due_date)}
            {exact ? ' · valor igual' : ''}
          </p>
        </div>
        <div className="flex shrink-0 flex-col items-end gap-1">
          {bankMismatch && <Badge variant="warning">Troca conta</Badge>}
          <Badge variant={account.type === 'receivable' ? 'success' : 'warning'}>
            {formatCurrency(account.remaining_amount)}
          </Badge>
        </div>
      </div>
    )
  }

  return (
    <>
      <Modal
        open={open}
        onClose={onClose}
        title="Conciliação"
        description={
          transaction
            ? `Transação de ${formatCurrency(transaction.value)} em ${formatDate(transaction.date)} (${targetBankName}). Selecione uma ou mais contas cuja soma seja igual ao valor.`
            : undefined
        }
        size="lg"
        footer={
          !creating ? (
            <div className="flex w-full flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
              <div className="text-sm text-muted">
                {selectedIds.length === 0 ? (
                  'Nenhuma conta selecionada'
                ) : (
                  <>
                    Selecionado: <span className="font-medium text-foreground">{formatCurrency(selectedTotal)}</span>
                    {Math.abs(difference) >= 0.01 && (
                      <>
                        {' '}
                        · diferença:{' '}
                        <span className={difference > 0 ? 'font-medium text-warning' : 'font-medium text-danger'}>
                          {formatCurrency(Math.abs(difference))}
                          {difference > 0 ? ' a menos' : ' a mais'}
                        </span>
                      </>
                    )}
                  </>
                )}
              </div>
              <div className="flex flex-col-reverse gap-2 sm:flex-row">
                <Button variant="ghost" onClick={openCreateForm}>
                  <Plus className="size-4" />
                  Criar lançamento
                  {selectedIds.length > 0 && difference > 0.009 ? ` (${formatCurrency(difference)})` : ''}
                </Button>
                <Button onClick={handleConfirm} disabled={!canConfirm} loading={reconcileMany.isPending}>
                  <Link2 className="size-4" />
                  Conciliar{selectedIds.length > 1 ? ` (${selectedIds.length})` : ''}
                </Button>
              </div>
            </div>
          ) : undefined
        }
      >
        {candidates.isPending && <Skeleton className="h-40" />}

        {!candidates.isPending && !creating && (
          <div className="space-y-3">
            {accountsChangingBank.length > 0 && (
              <Alert variant="warning" title="Conta bancária será alterada">
                <p>
                  {accountsChangingBank.length === 1
                    ? `O lançamento "${accountsChangingBank[0].description}" está em ${accountsChangingBank[0].bank_account ?? 'outra conta'} e passará para ${targetBankName}.`
                    : `${accountsChangingBank.length} lançamentos estão em outra conta e passarão para ${targetBankName}.`}
                </p>
              </Alert>
            )}

            <div className="relative">
              <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted" />
              <Input
                value={search}
                onChange={(event) => setSearch(event.target.value)}
                placeholder="Buscar por descrição, contraparte ou conta..."
                className="pl-9"
              />
            </div>

            {filteredAccounts.length === 0 ? (
              <p className="text-sm text-muted">Nenhum lançamento aberto encontrado.</p>
            ) : (
              <div className="max-h-80 space-y-2 overflow-y-auto">{filteredAccounts.map(renderAccountRow)}</div>
            )}
          </div>
        )}

        {!candidates.isPending && creating && (
          <Form form={form} onSubmit={handleCreate} className="space-y-4">
            {selectedIds.length > 0 ? (
              <Alert variant="info" title="Complemento da conciliação">
                <p>
                  {selectedIds.length} conta(s) selecionada(s) somam {formatCurrency(selectedTotal)}. O novo lançamento
                  deve ser de {formatCurrency(difference)} para fechar o extrato de {formatCurrency(txValue)}.
                </p>
              </Alert>
            ) : (
              transaction && (
                <p className="text-[13px] text-muted">
                  Os campos foram preenchidos com os dados do extrato — você pode ajustá-los antes de criar o lançamento.
                </p>
              )
            )}

            {accountsChangingBank.length > 0 && (
              <Alert variant="warning" title="Conta bancária será alterada">
                <p>
                  Ao confirmar, {accountsChangingBank.length === 1 ? 'o lançamento selecionado' : 'os lançamentos selecionados'}{' '}
                  passarão para {targetBankName}.
                </p>
              </Alert>
            )}

            <RadioGroupField
              name="type"
              options={[
                { value: 'payable', label: 'Despesa' },
                { value: 'receivable', label: 'Receita' },
              ]}
            />
            <div className="grid gap-4 sm:grid-cols-2">
              <TextField name="value" label="Valor" type="number" step="0.01" min="0" required />
              <TextField name="due_date" label="Data de vencimento" type="date" required />
            </div>
            <SelectField name="bank_account_id" label="Conta bancária" options={bankAccounts.data ?? []} placeholder="Selecione" required />
            <SearchSelectField
              name="cost_center_id"
              label="Centro de custo"
              loadOptions={loadCostCenters}
              resolveLabel={resolveCostCenterLabel}
              placeholder="Buscar centro de custo..."
              emptyMessage="Nenhum centro de custo encontrado"
            />
            <TextField name="description" label="Descrição" required />
            <CategorySearchSelectField
              name="category_id"
              label="Categoria"
              categoryType={categoryType}
              required
            />
            <div className="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
              <Button variant="secondary" onClick={() => setCreating(false)}>
                Voltar
              </Button>
              <Button type="submit" loading={createAccount.isPending}>
                {selectedIds.length > 0 ? 'Criar e conciliar' : 'Criar lançamento'}
              </Button>
            </div>
          </Form>
        )}
      </Modal>

      <ConfirmDialog
        open={confirmAction !== null}
        onClose={() => {
          setConfirmAction(null)
          setPendingCreate(null)
        }}
        onConfirm={handleConfirmDialog}
        title="Alterar conta bancária?"
        description={
          accountsChangingBank.length === 1
            ? `O lançamento "${accountsChangingBank[0]?.description}" será movido de ${accountsChangingBank[0]?.bank_account ?? 'outra conta'} para ${targetBankName}.`
            : `${accountsChangingBank.length} lançamentos serão movidos para ${targetBankName}.`
        }
        confirmLabel={confirmAction === 'create' ? 'Criar, alterar e conciliar' : 'Conciliar e alterar'}
        variant="primary"
        loading={reconcileMany.isPending || createAccount.isPending}
      />
    </>
  )
}
