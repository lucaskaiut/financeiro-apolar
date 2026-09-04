import { cn } from '@/shared/utils/cn'
import type { DateRange } from '@/shared/utils/date'
import { getVisiblePresets, resolvePresetRange } from '@/shared/utils/date-range'

export type { DateRange } from '@/shared/utils/date'

/**
 * Atalhos de período legados — preferir o DateRangeFilter unificado.
 */
export function DateRangeShortcuts({
  onApply,
  variant = 'range',
  className,
  allowAllTime = false,
}: {
  onApply: (range: DateRange) => void
  variant?: 'range' | 'single'
  className?: string
  allowAllTime?: boolean
}) {
  const presets = getVisiblePresets({ variant, allowAllTime })

  return (
    <div className={cn('flex flex-wrap gap-1.5', className)}>
      {presets.map((preset) => (
        <button
          key={preset.id}
          type="button"
          onClick={() => onApply(resolvePresetRange(preset.id))}
          className="rounded-full bg-surface-2 px-3 py-1.5 text-[13px] font-medium text-muted transition-colors hover:bg-surface-3 hover:text-foreground"
        >
          {preset.label}
        </button>
      ))}
    </div>
  )
}
