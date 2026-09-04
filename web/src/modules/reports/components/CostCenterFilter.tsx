import { Select } from '@/shared/design-system'
import { useCostCenterOptions } from '@/modules/cost-centers/hooks/useCostCenters'

interface CostCenterFilterProps {
  value: string
  onChange: (value: string) => void
  className?: string
}

export function CostCenterFilter({ value, onChange, className = 'w-52' }: CostCenterFilterProps) {
  const costCenters = useCostCenterOptions()

  return (
    <Select
      aria-label="Centro de custo"
      className={className}
      value={value}
      onChange={(event) => onChange(event.target.value)}
      options={[{ value: '', label: 'Todos os centros' }, ...(costCenters.data ?? [])]}
    />
  )
}

export function useCostCenterLabel(costCenterId: string): string {
  const costCenters = useCostCenterOptions()

  return costCenterId
    ? (costCenters.data?.find((option) => option.value === costCenterId)?.label ?? 'Centro de custo')
    : 'Todos os centros'
}
