import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { queryKeys } from '@/shared/constants/query-keys'
import { toast } from '@/shared/stores/toast.store'
import { reconciliationService, type ReconciliationListParams } from '../services/reconciliation.service'

export function useReconciliationQuery(params: ReconciliationListParams) {
  return useQuery({
    queryKey: queryKeys.reconciliation.list(params),
    queryFn: () => reconciliationService.list(params),
    placeholderData: keepPreviousData,
  })
}

export function useCandidates(id: string | undefined, from?: string, to?: string, exact = true) {
  return useQuery({
    queryKey: queryKeys.reconciliation.candidates(id ?? '', from, to, exact),
    queryFn: () => reconciliationService.candidates(id!, from, to, exact),
    enabled: !!id,
  })
}

export function useIdentify(params: { bank_account_id?: string; from?: string; to?: string }) {
  return useQuery({
    queryKey: queryKeys.reconciliation.identify(params),
    queryFn: () => reconciliationService.identify(params),
  })
}

function invalidate(queryClient: ReturnType<typeof useQueryClient>) {
  queryClient.invalidateQueries({ queryKey: queryKeys.reconciliation.all })
  queryClient.invalidateQueries({ queryKey: queryKeys.accounts.all })
  queryClient.invalidateQueries({ queryKey: ['cash-flow'] })
  queryClient.invalidateQueries({ queryKey: ['reports'] })
}

export function useImportOfx() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: ({ bankAccountId, content }: { bankAccountId: string; content: string }) =>
      reconciliationService.importOfx(bankAccountId, content),
    onSuccess: (result) => {
      invalidate(queryClient)
      toast.success('Importação concluída', `${result.imported} transações importadas.`)
    },
  })
}

export function useImportStatement() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: ({ bankAccountId, file }: { bankAccountId: string; file: File }) =>
      reconciliationService.importStatement(bankAccountId, file),
    onSuccess: (result) => {
      invalidate(queryClient)
      toast.success('Importação concluída', `${result.imported} transações importadas.`)
    },
  })
}

export function useAutoReconcile() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: ({ from, to }: { from?: string; to?: string }) => reconciliationService.auto(from, to),
    onSuccess: (result) => {
      invalidate(queryClient)
      toast.success('Conciliação automática', `${result.matched} conciliadas, ${result.ambiguous} ambíguas, ${result.not_found} sem correspondente.`)
    },
  })
}

export function useReconcile() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: ({ id, accountId }: { id: string; accountId: string }) => reconciliationService.reconcile(id, accountId),
    onSuccess: () => {
      invalidate(queryClient)
      toast.success('Transação conciliada', 'A baixa automática foi registrada.')
    },
  })
}

export function useReconcileMany() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: ({ transactions, accounts }: { transactions: string[]; accounts: string[] }) =>
      reconciliationService.reconcileMany(transactions, accounts),
    onSuccess: (_data, variables) => {
      invalidate(queryClient)
      const count = variables.accounts.length
      toast.success(
        'Transação conciliada',
        count > 1 ? `${count} lançamentos foram baixados.` : 'A baixa automática foi registrada.',
      )
    },
  })
}

export function useIgnoreTransaction() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: (id: string) => reconciliationService.ignore(id),
    onSuccess: () => {
      invalidate(queryClient)
      toast.success('Transação ignorada')
    },
  })
}

export function useUndoReconciliation() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: (id: string) => reconciliationService.undo(id),
    onSuccess: () => {
      invalidate(queryClient)
      toast.success('Conciliação desfeita', 'O lançamento foi reaberto.')
    },
  })
}

export function useCreateAccountFromTransaction() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: ({
      id,
      payload,
    }: {
      id: string
      payload: {
        type: 'payable' | 'receivable'
        description: string
        category_id: string
        bank_account_id?: string
        cost_center_id?: string | null
        value?: number
        due_date?: string
        account_ids?: string[]
      }
    }) => reconciliationService.createAccount(id, payload),
    onSuccess: (_data, variables) => {
      invalidate(queryClient)
      const linked = variables.payload.account_ids?.length ?? 0
      toast.success(
        linked > 0 ? 'Conciliação concluída' : 'Lançamento criado',
        linked > 0
          ? `Novo lançamento criado e ${linked + 1} contas baixadas.`
          : 'O lançamento foi criado a partir do extrato.',
      )
    },
  })
}
