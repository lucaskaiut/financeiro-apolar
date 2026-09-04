import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { queryKeys } from '@/shared/constants/query-keys'
import type { ListParams } from '@/shared/types/api'
import { toast } from '@/shared/stores/toast.store'
import { transfersService, type TransferPayload } from '../services/transfers.service'

export function useTransfersQuery(params: ListParams) {
  return useQuery({
    queryKey: queryKeys.transfers.list(params),
    queryFn: () => transfersService.list(params),
    placeholderData: keepPreviousData,
  })
}

export function useCreateTransfer() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: (payload: TransferPayload) => transfersService.create(payload),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: queryKeys.transfers.all })
      queryClient.invalidateQueries({ queryKey: ['cash-flow'] })
      toast.success('Transferência realizada', 'Os dois movimentos foram gerados.')
    },
  })
}

export function useDeleteTransfer() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: (id: string) => transfersService.remove(id),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: queryKeys.transfers.all })
      queryClient.invalidateQueries({ queryKey: ['cash-flow'] })
      toast.success('Transferência removida', 'A transferência foi excluída.')
    },
  })
}
