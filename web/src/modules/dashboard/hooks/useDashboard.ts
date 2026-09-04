import { useQuery } from '@tanstack/react-query'
import { queryKeys } from '@/shared/constants/query-keys'
import { dashboardService } from '../services/dashboard.service'

export function useDashboardSummary(bankAccountId?: string) {
  return useQuery({
    queryKey: [queryKeys.dashboard, bankAccountId ?? 'all'],
    queryFn: () => dashboardService.summary(bankAccountId),
  })
}
