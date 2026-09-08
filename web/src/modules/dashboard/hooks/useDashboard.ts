import { useQuery } from '@tanstack/react-query'
import { queryKeys } from '@/shared/constants/query-keys'
import { dashboardService } from '../services/dashboard.service'

export function useDashboardSummary(costCenterId?: string, bankAccountId?: string) {
  return useQuery({
    queryKey: [queryKeys.dashboard, costCenterId ?? 'all', bankAccountId ?? 'all'],
    queryFn: () => dashboardService.summary(costCenterId, bankAccountId),
  })
}
