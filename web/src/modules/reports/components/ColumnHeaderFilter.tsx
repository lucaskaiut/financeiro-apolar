import {
  useEffect,
  useId,
  useRef,
  useState,
  type CSSProperties,
  type KeyboardEvent,
} from 'react'
import { createPortal } from 'react-dom'
import { ArrowDownWideNarrow, ArrowUpWideNarrow, ListFilter, X } from 'lucide-react'
import { Input } from '@/shared/design-system'
import { cn } from '@/shared/utils/cn'
import {
  isColumnFilterActive,
  type ColumnFilter,
  type ColumnSortDirection,
} from '../utils/column-table'

type ColumnHeaderFilterProps = {
  label: string
  sortDirection: ColumnSortDirection | null
  onSort: (direction: ColumnSortDirection) => void
  onClear: () => void
  align?: 'start' | 'end'
} & (
  | {
      variant?: 'text'
      filter: ColumnFilter | undefined
      onFilterChange: (value: string) => void
      filterPlaceholder?: string
    }
  | {
      variant: 'range'
      filter: ColumnFilter | undefined
      onRangeChange: (range: { from: string; to: string }) => void
    }
)

export function ColumnHeaderFilter(props: ColumnHeaderFilterProps) {
  const {
    label,
    sortDirection,
    onSort,
    onClear,
    align = 'start',
    filter,
  } = props

  const variant = props.variant ?? 'text'
  const triggerId = useId()
  const panelId = useId()
  const containerRef = useRef<HTMLDivElement>(null)
  const panelRef = useRef<HTMLDivElement>(null)
  const inputRef = useRef<HTMLInputElement>(null)
  const [open, setOpen] = useState(false)
  const [panelStyle, setPanelStyle] = useState<CSSProperties>({})

  const textValue = filter?.kind === 'text' ? filter.value : ''
  const rangeFrom = filter?.kind === 'range' ? filter.from : ''
  const rangeTo = filter?.kind === 'range' ? filter.to : ''
  const isActive = isColumnFilterActive(filter) || sortDirection !== null

  const updatePosition = () => {
    const anchor = containerRef.current
    const panel = panelRef.current
    if (!anchor || !panel) return

    const rect = anchor.getBoundingClientRect()
    const panelWidth = 220
    const panelHeight = panel.offsetHeight || 180
    const spaceBelow = window.innerHeight - rect.bottom
    const openUp = spaceBelow < panelHeight + 12 && rect.top > panelHeight + 12
    const left =
      align === 'end'
        ? Math.min(rect.right - panelWidth, window.innerWidth - panelWidth - 8)
        : Math.max(8, Math.min(rect.left, window.innerWidth - panelWidth - 8))

    setPanelStyle({
      position: 'fixed',
      left: `${Math.max(8, left)}px`,
      top: openUp ? `${rect.top - 8}px` : `${rect.bottom + 8}px`,
      transform: openUp ? 'translateY(-100%)' : 'none',
      zIndex: 60,
      width: `${panelWidth}px`,
    })
  }

  useEffect(() => {
    if (!open) return

    updatePosition()
    const frame = window.requestAnimationFrame(updatePosition)
    inputRef.current?.focus()

    const onPointerDown = (event: PointerEvent) => {
      const target = event.target as Node
      if (containerRef.current?.contains(target) || panelRef.current?.contains(target)) return
      setOpen(false)
    }

    const onKeyDown = (event: globalThis.KeyboardEvent) => {
      if (event.key === 'Escape') setOpen(false)
    }

    const onReposition = () => updatePosition()

    document.addEventListener('pointerdown', onPointerDown)
    document.addEventListener('keydown', onKeyDown)
    window.addEventListener('resize', onReposition)
    window.addEventListener('scroll', onReposition, true)

    return () => {
      window.cancelAnimationFrame(frame)
      document.removeEventListener('pointerdown', onPointerDown)
      document.removeEventListener('keydown', onKeyDown)
      window.removeEventListener('resize', onReposition)
      window.removeEventListener('scroll', onReposition, true)
    }
  }, [open, align])

  useEffect(() => {
    if (open) updatePosition()
  }, [filter, sortDirection, open])

  const handleTriggerKeyDown = (event: KeyboardEvent<HTMLButtonElement>) => {
    if (event.key === 'ArrowDown' || event.key === 'Enter' || event.key === ' ') {
      event.preventDefault()
      setOpen(true)
    }
  }

  return (
    <div ref={containerRef} className="inline-flex max-w-full items-center gap-1.5">
      <span className="truncate">{label}</span>
      <button
        type="button"
        id={triggerId}
        aria-haspopup="dialog"
        aria-expanded={open}
        aria-controls={panelId}
        aria-label={`Filtrar e ordenar ${label}`}
        onClick={() => setOpen((current) => !current)}
        onKeyDown={handleTriggerKeyDown}
        className={cn(
          'inline-flex size-6 shrink-0 cursor-pointer items-center justify-center rounded-md transition-colors',
          isActive
            ? 'bg-primary-soft text-primary'
            : 'text-muted hover:bg-surface-3 hover:text-foreground',
        )}
      >
        <ListFilter className="size-3.5" aria-hidden="true" />
      </button>

      {open &&
        createPortal(
          <div
            ref={panelRef}
            id={panelId}
            role="dialog"
            aria-labelledby={triggerId}
            style={panelStyle}
            className="animate-rise-in rounded-xl border border-surface-3 bg-surface p-2.5 shadow-pop"
            onClick={(event) => event.stopPropagation()}
          >
            {variant === 'range' ? (
              <div className="mb-2.5 grid grid-cols-2 gap-2">
                <div>
                  <label className="mb-1 block text-[11px] font-medium text-muted normal-case tracking-normal">
                    De
                  </label>
                  <Input
                    ref={inputRef}
                    inputMode="decimal"
                    value={rangeFrom}
                    onChange={(event) =>
                      props.variant === 'range' &&
                      props.onRangeChange({ from: event.target.value, to: rangeTo })
                    }
                    placeholder="0"
                    className="h-8 px-2.5 text-[13px] normal-case tracking-normal"
                    aria-label={`${label} de`}
                  />
                </div>
                <div>
                  <label className="mb-1 block text-[11px] font-medium text-muted normal-case tracking-normal">
                    Até
                  </label>
                  <Input
                    inputMode="decimal"
                    value={rangeTo}
                    onChange={(event) =>
                      props.variant === 'range' &&
                      props.onRangeChange({ from: rangeFrom, to: event.target.value })
                    }
                    placeholder="0"
                    className="h-8 px-2.5 text-[13px] normal-case tracking-normal"
                    aria-label={`${label} até`}
                  />
                </div>
              </div>
            ) : (
              <Input
                ref={inputRef}
                value={textValue}
                onChange={(event) => props.variant !== 'range' && props.onFilterChange(event.target.value)}
                placeholder={props.variant !== 'range' ? (props.filterPlaceholder ?? 'Filtrar…') : undefined}
                className="mb-2.5 h-8 px-2.5 text-[13px] normal-case tracking-normal"
                aria-label={`Filtro de ${label}`}
              />
            )}

            <div className="space-y-0.5">
              <button
                type="button"
                onClick={() => onSort('asc')}
                className={cn(
                  'flex w-full cursor-pointer items-center gap-2 rounded-lg px-2 py-1.5 text-left text-[13px] normal-case tracking-normal transition-colors',
                  sortDirection === 'asc' ? 'bg-primary-soft font-medium text-primary' : 'text-foreground hover:bg-surface-2',
                )}
              >
                <ArrowUpWideNarrow className="size-3.5 shrink-0" aria-hidden="true" />
                Crescente
              </button>
              <button
                type="button"
                onClick={() => onSort('desc')}
                className={cn(
                  'flex w-full cursor-pointer items-center gap-2 rounded-lg px-2 py-1.5 text-left text-[13px] normal-case tracking-normal transition-colors',
                  sortDirection === 'desc' ? 'bg-primary-soft font-medium text-primary' : 'text-foreground hover:bg-surface-2',
                )}
              >
                <ArrowDownWideNarrow className="size-3.5 shrink-0" aria-hidden="true" />
                Decrescente
              </button>
              {isActive && (
                <button
                  type="button"
                  onClick={onClear}
                  className="flex w-full cursor-pointer items-center gap-2 rounded-lg px-2 py-1.5 text-left text-[13px] text-muted normal-case tracking-normal transition-colors hover:bg-surface-2 hover:text-foreground"
                >
                  <X className="size-3.5 shrink-0" aria-hidden="true" />
                  Limpar
                </button>
              )}
            </div>
          </div>,
          document.body,
        )}
    </div>
  )
}
