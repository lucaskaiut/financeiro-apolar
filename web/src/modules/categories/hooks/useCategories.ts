import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { queryKeys } from '@/shared/constants/query-keys'
import type { ListParams } from '@/shared/types/api'
import { toast } from '@/shared/stores/toast.store'
import { categoriesService, type CategoryPayload } from '../services/categories.service'

export function useCategoriesQuery(params: ListParams & { type?: string }) {
  return useQuery({
    queryKey: queryKeys.categories.list(params),
    queryFn: () => categoriesService.list(params),
    placeholderData: keepPreviousData,
  })
}

export function useCategoryOptions(type?: 'income' | 'expense') {
  return useQuery({
    queryKey: queryKeys.categories.list({ per_page: 100, type }),
    queryFn: () => categoriesService.list({ per_page: 100, type }),
    select: (data) => data.data.map((c) => ({ value: c.id, label: c.name, parent_id: c.parent_id, type: c.type })),
  })
}

export function useCategoryQuery(id: string | undefined) {
  return useQuery({
    queryKey: queryKeys.categories.detail(id ?? ''),
    queryFn: () => categoriesService.get(id!),
    enabled: !!id,
  })
}

export function useCreateCategory() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: (payload: CategoryPayload) => categoriesService.create(payload),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: queryKeys.categories.all })
      toast.success('Categoria criada', 'A categoria foi criada com sucesso.')
    },
  })
}

export function useUpdateCategory(id: string) {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: (payload: CategoryPayload) => categoriesService.update(id, payload),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: queryKeys.categories.all })
      toast.success('Categoria atualizada', 'As alterações foram salvas.')
    },
  })
}

export function useDeleteCategory() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: (id: string) => categoriesService.remove(id),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: queryKeys.categories.all })
      toast.success('Categoria removida', 'A categoria foi excluída.')
    },
  })
}
