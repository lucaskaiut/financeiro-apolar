import { formatCurrency } from '@/shared/utils/format'

interface ReportGroupHeaderProps {
  title: string
  total?: number
  subtitle?: string
}

export function ReportGroupHeader({ title, total, subtitle }: ReportGroupHeaderProps) {
  return (
    <div className="mb-2 flex flex-wrap items-center justify-between gap-2">
      <div>
        <h3 className="text-sm font-semibold text-foreground">{title}</h3>
        {subtitle && <p className="text-[13px] text-muted">{subtitle}</p>}
      </div>
      {total !== undefined && (
        <span className="text-[13px] font-medium text-foreground">{formatCurrency(total)}</span>
      )}
    </div>
  )
}
