import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { queryKeys } from '@/shared/constants/query-keys'
import type { ListParams } from '@/shared/types/api'
import { toast } from '@/shared/stores/toast.store'
import { recurrencesService, type RecurrencePayload } from '../services/recurrences.service'

export function useRecurrencesQuery(params: ListParams) {
  return useQuery({
    queryKey: queryKeys.recurrences.list(params),
    queryFn: () => recurrencesService.list(params),
    placeholderData: keepPreviousData,
  })
}

export function useRecurrenceQuery(id: string | undefined) {
  return useQuery({
    queryKey: queryKeys.recurrences.detail(id ?? ''),
    queryFn: () => recurrencesService.get(id!),
    enabled: !!id,
  })
}

function invalidate(queryClient: ReturnType<typeof useQueryClient>) {
  queryClient.invalidateQueries({ queryKey: queryKeys.recurrences.all })
  queryClient.invalidateQueries({ queryKey: queryKeys.accounts.all })
  queryClient.invalidateQueries({ queryKey: ['cash-flow'] })
}

export function useCreateRecurrence() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: (payload: RecurrencePayload) => recurrencesService.create(payload),
    onSuccess: () => {
      invalidate(queryClient)
      toast.success('Recorrência criada', 'A recorrência foi criada com sucesso.')
    },
  })
}

export function useUpdateRecurrence(id: string) {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: (payload: RecurrencePayload) => recurrencesService.update(id, payload),
    onSuccess: () => {
      invalidate(queryClient)
      toast.success('Recorrência atualizada', 'As alterações foram salvas.')
    },
  })
}

export function useDeleteRecurrence() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: (id: string) => recurrencesService.remove(id),
    onSuccess: () => {
      invalidate(queryClient)
      toast.success('Recorrência removida', 'A recorrência foi excluída.')
    },
  })
}
