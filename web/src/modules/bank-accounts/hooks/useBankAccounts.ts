import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { queryKeys } from '@/shared/constants/query-keys'
import type { ListParams } from '@/shared/types/api'
import { toast } from '@/shared/stores/toast.store'
import { bankAccountsService, type BankAccountPayload } from '../services/bank-accounts.service'

export function useBankAccountsQuery(params: ListParams) {
  return useQuery({
    queryKey: queryKeys.bankAccounts.list(params),
    queryFn: () => bankAccountsService.list(params),
    placeholderData: keepPreviousData,
  })
}

export function useBankAccountOptions() {
  return useQuery({
    queryKey: queryKeys.bankAccounts.list({ per_page: 100 }),
    queryFn: () => bankAccountsService.list({ per_page: 100 }),
    select: (data) => data.data.map((ba) => ({ value: ba.id, label: ba.name })),
  })
}

export function useBankAccountQuery(id: string | undefined) {
  return useQuery({
    queryKey: queryKeys.bankAccounts.detail(id ?? ''),
    queryFn: () => bankAccountsService.get(id!),
    enabled: !!id,
  })
}

export function useCreateBankAccount() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: (payload: BankAccountPayload) => bankAccountsService.create(payload),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: queryKeys.bankAccounts.all })
      toast.success('Conta bancária criada', 'A conta bancária foi criada com sucesso.')
    },
  })
}

export function useUpdateBankAccount(id: string) {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: (payload: BankAccountPayload) => bankAccountsService.update(id, payload),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: queryKeys.bankAccounts.all })
      toast.success('Conta bancária atualizada', 'As alterações foram salvas.')
    },
  })
}

export function useDeleteBankAccount() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: (id: string) => bankAccountsService.remove(id),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: queryKeys.bankAccounts.all })
      toast.success('Conta bancária removida', 'A conta bancária foi excluída.')
    },
  })
}
