import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { queryKeys } from '@/shared/constants/query-keys'
import type { ListParams } from '@/shared/types/api'
import { toast } from '@/shared/stores/toast.store'
import { companiesService, type CompanyPayload } from '../services/companies.service'

export function useCompaniesQuery(params: ListParams) {
  return useQuery({
    queryKey: queryKeys.companies.list(params),
    queryFn: () => companiesService.list(params),
    placeholderData: keepPreviousData,
  })
}

export function useCompanyOptions() {
  return useQuery({
    queryKey: queryKeys.companies.list({ per_page: 100 }),
    queryFn: () => companiesService.list({ per_page: 100 }),
    select: (data) => data.data.map((c) => ({ value: c.id, label: c.name })),
  })
}

export function useCompanyQuery(id: string | undefined) {
  return useQuery({
    queryKey: queryKeys.companies.detail(id ?? ''),
    queryFn: () => companiesService.get(id!),
    enabled: !!id,
  })
}

export function useCreateCompany() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: (payload: CompanyPayload) => companiesService.create(payload),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: queryKeys.companies.all })
      toast.success('Empresa criada', 'A empresa foi criada com sucesso.')
    },
  })
}

export function useUpdateCompany(id: string) {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: (payload: CompanyPayload) => companiesService.update(id, payload),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: queryKeys.companies.all })
      toast.success('Empresa atualizada', 'As alterações foram salvas.')
    },
  })
}

export function useDeleteCompany() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: (id: string) => companiesService.remove(id),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: queryKeys.companies.all })
      toast.success('Empresa removida', 'A empresa foi excluída.')
    },
  })
}
