import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { queryKeys } from '@/shared/constants/query-keys'
import type { ListParams } from '@/shared/types/api'
import { toast } from '@/shared/stores/toast.store'
import { installmentsService, type InstallmentPayload } from '../services/installments.service'

export function useInstallmentsQuery(params: ListParams) {
  return useQuery({
    queryKey: queryKeys.installments.list(params),
    queryFn: () => installmentsService.list(params),
    placeholderData: keepPreviousData,
  })
}

export function useInstallmentQuery(id: string | undefined) {
  return useQuery({
    queryKey: queryKeys.installments.detail(id ?? ''),
    queryFn: () => installmentsService.get(id!),
    enabled: !!id,
  })
}

export function useUpdateInstallment(id: string) {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: (payload: InstallmentPayload) => installmentsService.update(id, payload),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: queryKeys.installments.all })
      queryClient.invalidateQueries({ queryKey: queryKeys.accounts.all })
      queryClient.invalidateQueries({ queryKey: ['cash-flow'] })
      queryClient.invalidateQueries({ queryKey: ['reports'] })
      toast.success('Parcelamento atualizado', 'As alterações foram salvas.')
    },
  })
}
