import { formatCurrency } from '@/shared/utils/format'

export function formatProvisionAmount(value: number | null | undefined): string {
  if (value === null || value === undefined) {
    return '-'
  }

  return formatCurrency(value)
}

export function provisionAccountAmountClass(value: number | null | undefined): string {
  if (value === null || value === undefined) {
    return 'text-muted'
  }

  return 'text-foreground'
}

export function provisionTotalAmountClass(value: number | null | undefined): string {
  if (value === null || value === undefined) {
    return 'text-muted'
  }

  return 'text-danger'
}
