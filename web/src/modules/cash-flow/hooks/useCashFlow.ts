import { useQuery } from '@tanstack/react-query'
import { queryKeys } from '@/shared/constants/query-keys'
import { cashFlowService } from '../services/cash-flow.service'

export function useRealizedCashFlow(params: Record<string, string>) {
  return useQuery({
    queryKey: queryKeys.cashFlow.realized(params),
    queryFn: () => cashFlowService.realized(params),
  })
}

export function useProjectedCashFlow(params: Record<string, string | number>) {
  return useQuery({
    queryKey: queryKeys.cashFlow.projected(params),
    queryFn: () => cashFlowService.projected(params),
  })
}
