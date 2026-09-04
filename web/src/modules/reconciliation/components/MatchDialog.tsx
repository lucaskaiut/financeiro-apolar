import { useCallback, useEffect, useState } from 'react'
import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { z } from 'zod'
import { Link2, Plus } from 'lucide-react'
import {
  Badge,
  Button,
  Form,
  Modal,
  RadioGroupField,
  SearchSelectField,
  SelectField,
  Skeleton,
  TextField,
  type SearchSelectOption,
} from '@/shared/design-system'
import { formatCurrency, formatDate } from '@/shared/utils/format'
import { categoriesService } from '@/modules/categories/services/categories.service'
import { useCostCenterOptions } from '@/modules/cost-centers/hooks/useCostCenters'
import type { BankTransaction } from '@/shared/types/models'
import { useCandidates, useCreateAccountFromTransaction, useReconcile } from '../hooks/useReconciliation'

const createSchema = z.object({
  type: z.enum(['payable', 'receivable']),
  description: z.string().min(1, 'Informe a descrição'),
  category_id: z.string().min(1, 'Selecione a categoria'),
  cost_center_id: z.string().min(1, 'Selecione o centro de custo'),
  value: z.string().refine((v) => v !== '' && Number(v) > 0, 'Informe um valor válido'),
  due_date: z.string().min(1, 'Informe a data'),
})

type CreateFormValues = z.infer<typeof createSchema>

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
  const candidates = useCandidates(open && transaction ? transaction.id : undefined, from || undefined, to || undefined)
  const reconcile = useReconcile()
  const createAccount = useCreateAccountFromTransaction()
  const costCenters = useCostCenterOptions()

  const [creating, setCreating] = useState(false)

  const form = useForm<CreateFormValues>({
    resolver: zodResolver(createSchema),
    defaultValues: {
      type: 'payable',
      description: '',
      category_id: '',
      cost_center_id: '',
      value: '',
      due_date: '',
    },
  })

  useEffect(() => {
    if (!transaction) return

    form.reset({
      type: transaction.type === 'credit' ? 'receivable' : 'payable',
      description: transaction.description ?? '',
      category_id: '',
      cost_center_id: transaction.cost_center_id ?? '',
      value: String(transaction.value ?? ''),
      due_date: transaction.date ?? '',
    })
  }, [transaction, form])

  const type = form.watch('type')
  const categoryType = type === 'receivable' ? 'income' : 'expense'

  const loadCategories = useCallback(
    async (search: string): Promise<SearchSelectOption[]> => {
      const result = await categoriesService.list({
        search: search || undefined,
        type: categoryType,
        parent: 'root',
        per_page: 20,
      })

      return result.data.map((category) => ({ value: category.id, label: category.name }))
    },
    [categoryType],
  )

  const hasCandidates = (candidates.data?.candidates.length ?? 0) > 0

  const handlePick = (accountId: string) => {
    if (!transaction) return
    reconcile.mutate({ id: transaction.id, accountId }, { onSettled: onClose })
  }

  const handleCreate = async (values: CreateFormValues) => {
    if (!transaction) return
    await createAccount.mutateAsync({
      id: transaction.id,
      payload: {
        type: values.type,
        description: values.description,
        category_id: values.category_id,
        cost_center_id: values.cost_center_id || undefined,
        value: Number(values.value),
        due_date: values.due_date,
      },
    })
    onClose()
  }

  return (
    <Modal
      open={open}
      onClose={onClose}
      title="Conciliação"
      description={transaction ? `Transação de ${formatCurrency(transaction.value)} em ${formatDate(transaction.date)}` : undefined}
      size="lg"
    >
      {candidates.isPending && <Skeleton className="h-40" />}

      {!candidates.isPending && !creating && hasCandidates && (
        <div className="space-y-2">
          <p className="text-sm text-muted">Selecione o lançamento correspondente:</p>
          {candidates.data?.candidates.map((account) => (
            <button
              key={account.id}
              type="button"
              onClick={() => handlePick(account.id)}
              className="flex w-full items-center justify-between gap-3 rounded-lg bg-surface-2 p-3 text-left transition-colors hover:bg-surface-3"
            >
              <div className="min-w-0">
                <p className="truncate text-sm font-medium text-foreground">{account.description}</p>
                <p className="truncate text-[13px] text-muted">
                  {account.cost_center} · vencimento {formatDate(account.due_date)}
                </p>
              </div>
              <Badge variant={account.type === 'receivable' ? 'success' : 'warning'}>
                {formatCurrency(account.remaining_amount)}
              </Badge>
            </button>
          ))}
        </div>
      )}

      {!candidates.isPending && !creating && !hasCandidates && (
        <div className="space-y-3">
          <p className="text-sm text-muted">Nenhum lançamento correspondente encontrado.</p>
          <Button variant="secondary" onClick={() => setCreating(true)}>
            <Plus className="size-4" />
            Criar lançamento a partir do extrato
          </Button>
        </div>
      )}

      {!candidates.isPending && creating && (
        <Form form={form} onSubmit={handleCreate} className="space-y-4">
          {transaction && (
            <p className="text-[13px] text-muted">
              Os campos foram preenchidos com os dados do extrato — você pode ajustá-los antes de criar o lançamento.
            </p>
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
          <SelectField name="cost_center_id" label="Centro de custo" options={costCenters.data ?? []} placeholder="Selecione" required />
          <TextField name="description" label="Descrição" required />
          <SearchSelectField
            name="category_id"
            label="Categoria"
            loadOptions={loadCategories}
            placeholder="Buscar categoria..."
            required
          />
          <div className="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
            <Button variant="secondary" onClick={() => setCreating(false)}>
              Voltar
            </Button>
            <Button type="submit" loading={createAccount.isPending}>
              Criar lançamento
            </Button>
          </div>
        </Form>
      )}

      {!creating && hasCandidates && (
        <div className="mt-3 flex justify-end gap-2">
          <Button variant="ghost" onClick={() => setCreating(true)}>
            <Link2 className="size-4" />
            Criar novo lançamento
          </Button>
        </div>
      )}
    </Modal>
  )
}
