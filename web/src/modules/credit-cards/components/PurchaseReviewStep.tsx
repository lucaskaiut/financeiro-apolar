import { useState } from 'react'
import { Button, Card, CardContent } from '@/shared/design-system'
import { CheckCircle2, ChevronDown, ChevronRight, Eye, EyeOff, Layers, TriangleAlert } from 'lucide-react'
import { formatCurrency, formatDate } from '@/shared/utils/format'
import { CategorySelect, SubcategorySelect, CostCenterSelect } from './ClassificationSelects'
import { SplitEditor } from './SplitEditor'
import { emptySplit, round, type PurchaseDraft } from '../types/import-draft'
import { cn } from '@/shared/utils/cn'

export function PurchaseReviewStep({
  items,
  onChange,
}: {
  items: PurchaseDraft[]
  onChange: (items: PurchaseDraft[]) => void
}) {
  const [expanded, setExpanded] = useState<Set<string>>(new Set())

  const updateItem = (id: string, patch: Partial<PurchaseDraft>) => {
    onChange(items.map((item) => (item.id === id ? { ...item, ...patch } : item)))
  }

  const toggleIgnore = (id: string) => {
    onChange(
      items.map((item) =>
        item.id === id ? { ...item, status: item.status === 'ignored' ? 'normal' : 'ignored' } : item,
      ),
    )
  }

  const toggleSplit = (id: string) => {
    onChange(
      items.map((item) => {
        if (item.id !== id) return item

        if (item.splits.length > 0) {
          return { ...item, splits: [] }
        }

        const equal = round(item.value / 2)
        const parts = [
          { ...emptySplit(equal), description: item.description, category_id: item.category_id, subcategory_id: item.subcategory_id, cost_center_id: item.cost_center_id },
          { ...emptySplit(round(item.value - equal)), description: item.description, category_id: item.category_id, subcategory_id: item.subcategory_id, cost_center_id: item.cost_center_id },
        ]

        return { ...item, splits: parts }
      }),
    )

    setExpanded((current) => {
      const next = new Set(current)
      if (next.has(id)) next.delete(id)
      else next.add(id)
      return next
    })
  }

  const setSplits = (id: string, splits: PurchaseDraft['splits']) => {
    onChange(items.map((item) => (item.id === id ? { ...item, splits } : item)))
  }

  const rateadaCount = items.filter((item) => item.splits.length > 0).length
  const ignoredCount = items.filter((item) => item.status === 'ignored').length
  const importableCount = items.filter((item) => item.status !== 'ignored').length
  const importableTotal = round(
    items.filter((item) => item.status !== 'ignored').reduce((sum, item) => sum + item.value, 0),
  )

  return (
    <Card className="border border-surface-2">
      <CardContent className="space-y-4 p-6">
        <div className="flex flex-wrap items-center gap-2 text-sm text-muted">
          <span className="font-medium text-foreground">{items.length} compras</span>
          <span>·</span>
          <span>{importableCount} a importar</span>
          <span>·</span>
          <span>{ignoredCount} ignoradas</span>
          <span>·</span>
          <span>{rateadaCount} rateadas</span>
          <span className="ml-auto font-medium text-foreground">{formatCurrency(importableTotal)}</span>
        </div>

        <div className="hidden gap-3 px-1 text-xs font-medium tracking-wide text-muted uppercase lg:grid lg:grid-cols-[minmax(0,2.2fr)_minmax(0,1.4fr)_minmax(0,1.4fr)_minmax(0,1.2fr)_7rem_9rem]">
          <span>Compra</span>
          <span>Categoria</span>
          <span>Subcategoria</span>
          <span>Centro de custo</span>
          <span className="text-right">Valor</span>
          <span className="text-right">Ações</span>
        </div>

        <div className="divide-y divide-surface-2">
          {items.map((item) => {
            const isIgnored = item.status === 'ignored'
            const isRateada = item.splits.length > 0
            const isExpanded = expanded.has(item.id)

            return (
              <div key={item.id} className={cn('py-3', isIgnored && 'opacity-50')}>
                <div className="grid grid-cols-1 gap-3 lg:grid-cols-[minmax(0,2.2fr)_minmax(0,1.4fr)_minmax(0,1.4fr)_minmax(0,1.2fr)_7rem_9rem] lg:items-center">
                  <div className="min-w-0">
                    <p className={cn('truncate font-medium text-foreground', isIgnored && 'line-through')}>{item.description}</p>
                    <p className="text-[12px] text-muted">
                      {formatDate(item.purchase_date)}
                      {item.is_duplicate && item.existing && (
                        <span className="ml-2 inline-flex items-center gap-1 text-warning">
                          <TriangleAlert className="size-3" />
                          Possível duplicata: {formatDate(item.existing.date)} · {formatCurrency(item.existing.value)}
                        </span>
                      )}
                    </p>
                  </div>

                  {isRateada ? (
                    <span className="text-muted lg:text-center">—</span>
                  ) : (
                    <CategorySelect
                      value={item.category_id}
                      onChange={(categoryId) => updateItem(item.id, { category_id: categoryId, subcategory_id: '' })}
                    />
                  )}

                  {isRateada ? (
                    <span className="text-muted lg:text-center">—</span>
                  ) : (
                    <SubcategorySelect
                      value={item.subcategory_id}
                      categoryId={item.category_id}
                      onChange={(subcategoryId) => updateItem(item.id, { subcategory_id: subcategoryId })}
                    />
                  )}

                  {isRateada ? (
                    <span className="text-muted lg:text-center">—</span>
                  ) : (
                    <CostCenterSelect value={item.cost_center_id} onChange={(costCenterId) => updateItem(item.id, { cost_center_id: costCenterId })} />
                  )}

                  <div className="text-right">
                    <span className="font-medium text-foreground tabular-nums">{formatCurrency(item.value)}</span>
                    {isRateada && (
                      <span className="ml-1.5 inline-flex items-center gap-1 text-xs text-primary">
                        <Layers className="size-3.5" />
                        Rateada
                      </span>
                    )}
                  </div>

                  <div className="flex items-center gap-1 lg:justify-end">
                    <Button
                      type="button"
                      variant="ghost"
                      size="sm"
                      onClick={() => toggleSplit(item.id)}
                      aria-label={isRateada ? 'Desfazer rateio' : 'Ratear compra'}
                      title={isRateada ? 'Desfazer rateio' : 'Ratear compra'}
                    >
                      <Layers className="size-4" />
                    </Button>
                    {isRateada && (
                      <Button
                        type="button"
                        variant="ghost"
                        size="sm"
                        onClick={() =>
                          setExpanded((current) => {
                            const next = new Set(current)
                            if (next.has(item.id)) next.delete(item.id)
                            else next.add(item.id)
                            return next
                          })
                        }
                        aria-label={isExpanded ? 'Ocultar partes' : 'Ver partes'}
                      >
                        {isExpanded ? <ChevronDown className="size-4" /> : <ChevronRight className="size-4" />}
                      </Button>
                    )}
                    <Button
                      type="button"
                      variant="ghost"
                      size="sm"
                      className={isIgnored ? '' : 'text-warning hover:bg-warning-soft hover:text-warning'}
                      onClick={() => toggleIgnore(item.id)}
                      aria-label={isIgnored ? 'Reimportar compra' : 'Ignorar compra'}
                      title={isIgnored ? 'Reimportar' : 'Ignorar'}
                    >
                      {isIgnored ? <Eye className="size-4" /> : <EyeOff className="size-4" />}
                    </Button>
                  </div>
                </div>

                {isRateada && isExpanded && (
                  <div className="mt-3 rounded-lg bg-surface-2/40 p-3">
                    <SplitEditor splits={item.splits} total={item.value} onChange={(splits) => setSplits(item.id, splits)} />
                  </div>
                )}
              </div>
            )
          })}
        </div>

        {importableCount === 0 && (
          <p className="flex items-center gap-2 text-sm text-muted">
            <CheckCircle2 className="size-4" />
            Todas as compras estão marcadas como ignoradas.
          </p>
        )}
      </CardContent>
    </Card>
  )
}
