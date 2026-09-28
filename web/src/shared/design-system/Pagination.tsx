import { ChevronLeft, ChevronRight } from 'lucide-react'
import type { PaginationMeta } from '@/shared/types/api'
import { cn } from '@/shared/utils/cn'
import { Button } from './Button'
import { Select } from './Select'

export function Pagination({
  meta,
  onPageChange,
  perPage,
  onPerPageChange,
  perPageOptions = [10, 25, 50, 100, 200],
  className,
}: {
  meta: PaginationMeta
  onPageChange: (page: number) => void
  perPage?: number
  onPerPageChange?: (perPage: number) => void
  perPageOptions?: number[]
  className?: string
}) {
  if (meta.total === 0) return null

  const showPerPage = perPage !== undefined && onPerPageChange !== undefined

  return (
    <nav
      aria-label="Paginação"
      className={cn('flex flex-wrap items-center justify-between gap-3', className)}
    >
      <div className="flex flex-wrap items-center gap-4">
        <p className="text-[13px] text-muted">
          Exibindo <span className="font-medium text-foreground">{meta.from ?? 0}</span>–
          <span className="font-medium text-foreground">{meta.to ?? 0}</span> de{' '}
          <span className="font-medium text-foreground">{meta.total}</span>
        </p>

        {showPerPage && (
          <label className="flex items-center gap-2 text-[13px] text-muted">
            Itens por página
            <Select
              aria-label="Itens por página"
              value={String(perPage)}
              options={perPageOptions.map((option) => ({ value: String(option), label: String(option) }))}
              onChange={(event) => onPerPageChange(Number(event.target.value))}
              className="h-8 w-20 pr-8 pl-2.5 text-[13px]"
            />
          </label>
        )}
      </div>

      <div className="flex items-center gap-2">
        <Button
          variant="secondary"
          size="sm"
          disabled={meta.current_page <= 1}
          onClick={() => onPageChange(meta.current_page - 1)}
          aria-label="Página anterior"
        >
          <ChevronLeft className="size-4" />
          Anterior
        </Button>
        <span className="px-1 text-[13px] text-muted">
          {meta.current_page} / {meta.last_page}
        </span>
        <Button
          variant="secondary"
          size="sm"
          disabled={meta.current_page >= meta.last_page}
          onClick={() => onPageChange(meta.current_page + 1)}
          aria-label="Próxima página"
        >
          Próxima
          <ChevronRight className="size-4" />
        </Button>
      </div>
    </nav>
  )
}
