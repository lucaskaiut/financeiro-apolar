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

export function useCandidates(id: string | undefined, from?: string, to?: string) {
  return useQuery({
    queryKey: queryKeys.reconciliation.candidates(id ?? '', from, to),
    queryFn: () => reconciliationService.candidates(id!, from, to),
    enabled: !!id,
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
    mutationFn: ({ costCenterId, content }: { costCenterId: string; content: string }) =>
      reconciliationService.importOfx(costCenterId, content),
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
        cost_center_id?: string
        value?: number
        due_date?: string
      }
    }) => reconciliationService.createAccount(id, payload),
    onSuccess: () => {
      invalidate(queryClient)
      toast.success('Lançamento criado', 'O lançamento foi criado a partir do extrato.')
    },
  })
}
