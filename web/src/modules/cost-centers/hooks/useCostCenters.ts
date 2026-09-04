import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { queryKeys } from '@/shared/constants/query-keys'
import type { ListParams } from '@/shared/types/api'
import { toast } from '@/shared/stores/toast.store'
import { costCentersService, type CostCenterPayload } from '../services/cost-centers.service'

export function useCostCentersQuery(params: ListParams) {
  return useQuery({
    queryKey: queryKeys.costCenters.list(params),
    queryFn: () => costCentersService.list(params),
    placeholderData: keepPreviousData,
  })
}

export function useCostCenterOptions() {
  return useQuery({
    queryKey: queryKeys.costCenters.list({ per_page: 100 }),
    queryFn: () => costCentersService.list({ per_page: 100 }),
    select: (data) => data.data.map((cc) => ({ value: cc.id, label: cc.name })),
  })
}

export function useCostCenterQuery(id: string | undefined) {
  return useQuery({
    queryKey: queryKeys.costCenters.detail(id ?? ''),
    queryFn: () => costCentersService.get(id!),
    enabled: !!id,
  })
}

export function useCreateCostCenter() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: (payload: CostCenterPayload) => costCentersService.create(payload),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: queryKeys.costCenters.all })
      toast.success('Centro de custo criado', 'O centro de custo foi criado com sucesso.')
    },
  })
}

export function useUpdateCostCenter(id: string) {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: (payload: CostCenterPayload) => costCentersService.update(id, payload),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: queryKeys.costCenters.all })
      toast.success('Centro de custo atualizado', 'As alterações foram salvas.')
    },
  })
}

export function useDeleteCostCenter() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: (id: string) => costCentersService.remove(id),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: queryKeys.costCenters.all })
      toast.success('Centro de custo removido', 'O centro de custo foi excluído.')
    },
  })
}
