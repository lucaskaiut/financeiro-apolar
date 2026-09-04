import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { queryKeys, type AccountListParams } from '@/shared/constants/query-keys'
import { toast } from '@/shared/stores/toast.store'
import { isApiError } from '@/shared/api/errors'
import { accountsService, type AccountPayload, type SettlePayload } from '../services/accounts.service'

export function useAccountsQuery(params: AccountListParams) {
  return useQuery({
    queryKey: queryKeys.accounts.list(params),
    queryFn: () => accountsService.list(params),
    placeholderData: keepPreviousData,
  })
}

export function useAccountQuery(id: string | undefined) {
  return useQuery({
    queryKey: queryKeys.accounts.detail(id ?? ''),
    queryFn: () => accountsService.get(id!),
    enabled: !!id,
  })
}

function invalidateAccounts(queryClient: ReturnType<typeof useQueryClient>) {
  queryClient.invalidateQueries({ queryKey: queryKeys.accounts.all })
  queryClient.invalidateQueries({ queryKey: ['cash-flow'] })
  queryClient.invalidateQueries({ queryKey: ['reports'] })
  queryClient.invalidateQueries({ queryKey: ['reconciliation'] })
}

export function useCreateAccount() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: (payload: AccountPayload) => accountsService.create(payload),
    onSuccess: (data) => {
      invalidateAccounts(queryClient)
      const count = data.data.length
      toast.success('Conta criada', count > 1 ? `${count} parcelas geradas com sucesso.` : 'A conta foi criada com sucesso.')
    },
  })
}

export function useImportAccounts() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: ({ file, costCenterId }: { file: File; costCenterId: string }) =>
      accountsService.importXlsx(file, costCenterId),
    onSuccess: () => {
      invalidateAccounts(queryClient)
      toast.success('Importação iniciada', 'A planilha será processada em segundo plano. Atualize a lista em instantes.')
    },
  })
}

export function useUpdateAccount(id: string) {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: (payload: Partial<AccountPayload>) => accountsService.update(id, payload),
    onSuccess: () => {
      invalidateAccounts(queryClient)
      toast.success('Conta atualizada', 'As alterações foram salvas.')
    },
  })
}

export function useDeleteAccount() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: (id: string) => accountsService.remove(id),
    onSuccess: () => {
      invalidateAccounts(queryClient)
      toast.success('Conta removida', 'A conta foi excluída.')
    },
  })
}

export function useSettleAccount(id: string) {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: (payload: SettlePayload) => accountsService.settle(id, payload),
    onSuccess: () => {
      invalidateAccounts(queryClient)
      toast.success('Baixa registrada', 'A baixa foi registrada com sucesso.')
    },
  })
}

export function useUnsettleAccount(id: string) {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: (settlementId: string) => accountsService.unsettle(id, settlementId),
    onSuccess: () => {
      invalidateAccounts(queryClient)
      toast.success('Baixa removida', 'A baixa foi removida.')
    },
  })
}

export function useReopenAccount(id: string) {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: () => accountsService.reopen(id),
    onSuccess: () => {
      invalidateAccounts(queryClient)
      queryClient.invalidateQueries({ queryKey: queryKeys.accounts.detail(id) })
      toast.success('Conta reaberta', 'Todas as baixas foram revertidas e a conta voltou ao status em aberto.')
    },
  })
}

export function useCancelAccount() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: (id: string) => accountsService.cancel(id),
    onSuccess: () => {
      invalidateAccounts(queryClient)
      toast.success('Conta cancelada', 'A conta foi cancelada.')
    },
  })
}

export function useAccountDocuments(accountId: string) {
  return useQuery({
    queryKey: queryKeys.accounts.documents(accountId),
    queryFn: () => accountsService.listDocuments(accountId),
    enabled: !!accountId,
  })
}

function invalidateDocuments(queryClient: ReturnType<typeof useQueryClient>, accountId: string) {
  queryClient.invalidateQueries({ queryKey: queryKeys.accounts.documents(accountId) })
}

export function useUploadDocuments(accountId: string) {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: (files: File[]) => accountsService.uploadDocuments(accountId, files),
    onSuccess: (documents) => {
      invalidateDocuments(queryClient, accountId)
      const count = documents.length
      toast.success('Documentos anexados', count > 1 ? `${count} documentos anexados com sucesso.` : 'Documento anexado com sucesso.')
    },
    onError: (error) => {
      toast.error('Falha no upload', isApiError(error) ? error.message : 'Não foi possível anexar os documentos.')
    },
  })
}

export function useDeleteDocument(accountId: string) {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: (documentId: string) => accountsService.removeDocument(accountId, documentId),
    onSuccess: () => {
      invalidateDocuments(queryClient, accountId)
      toast.success('Documento removido', 'O documento foi removido.')
    },
  })
}
