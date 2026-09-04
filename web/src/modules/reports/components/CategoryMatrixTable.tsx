import { cn } from '@/shared/utils/cn'
import type { CategoryMatrix, CategoryMatrixTotals } from '../services/reports.service'
import { categoryAmountClass, categoryTotalAmountClass, categoryTotalColumnAmountClass, formatCategoryAmount } from '../utils/category-format'

interface CategoryMatrixTableProps {
  matrix: CategoryMatrix
  compact?: boolean
  className?: string
}

const labelColumnClass = 'min-w-80 w-80 whitespace-nowrap'

export function CategoryMatrixTable({ matrix, compact = false, className }: CategoryMatrixTableProps) {
  const cellClass = compact ? 'px-2 py-1 text-[11px]' : 'px-3 py-2 text-sm'
  const headerClass = compact ? 'px-2 py-1.5 text-[10px]' : 'px-3 py-2 text-xs'

  return (
    <div className={cn('overflow-x-auto', className)}>
      <table className="min-w-full border-collapse">
        <thead>
          <tr className="border-b border-surface-3 bg-surface-2/80">
            <th
              className={cn(
                'sticky left-0 z-30 bg-surface-2/95 text-left font-semibold tracking-wide text-muted uppercase',
                labelColumnClass,
                headerClass,
              )}
            >
              Descrição
            </th>
            {matrix.columns.map((column) => (
              <th
                key={column.key}
                className={cn(
                  'whitespace-nowrap text-right font-semibold tracking-wide text-muted uppercase',
                  headerClass,
                )}
              >
                {column.label}
              </th>
            ))}
            <th className={cn('whitespace-nowrap text-right font-semibold tracking-wide text-muted uppercase', headerClass)}>
              Total geral
            </th>
          </tr>
        </thead>
        <tbody>
          {matrix.groups.map((group) => (
            <CostCenterRows key={group.cost_center} group={group} matrix={matrix} cellClass={cellClass} compact={compact} />
          ))}

          <GrandTotalRow matrix={matrix} cellClass={cellClass} compact={compact} />
        </tbody>
      </table>
    </div>
  )
}

function AmountCells({
  totals,
  matrix,
  cellClass,
  highlightAllValues = false,
}: {
  totals: CategoryMatrixTotals
  matrix: CategoryMatrix
  cellClass: string
  highlightAllValues?: boolean
}) {
  return (
    <>
      {matrix.columns.map((column) => {
        const value = totals.amounts[column.key]
        const valueClass = highlightAllValues ? categoryTotalAmountClass(value) : categoryAmountClass(value)

        return (
          <td key={column.key} className={cn('whitespace-nowrap text-right tabular-nums', cellClass, valueClass)}>
            {formatCategoryAmount(value)}
          </td>
        )
      })}
      <td className={cn('whitespace-nowrap text-right tabular-nums', cellClass, highlightAllValues ? categoryTotalAmountClass(totals.total) : categoryTotalColumnAmountClass(totals.total))}>
        {formatCategoryAmount(totals.total)}
      </td>
    </>
  )
}

function CostCenterRows({
  group,
  matrix,
  cellClass,
  compact,
}: {
  group: CategoryMatrix['groups'][number]
  matrix: CategoryMatrix
  cellClass: string
  compact: boolean
}) {
  return (
    <>
      <tr className="border-t border-surface-3 bg-blue-100/80 dark:bg-blue-950/40">
        <td
          colSpan={matrix.columns.length + 2}
          className={cn('sticky left-0 z-10 bg-blue-100/95 font-semibold text-foreground dark:bg-blue-950/90', cellClass, 'whitespace-nowrap')}
        >
          {group.cost_center}
        </td>
      </tr>

      {group.categories.map((category) => (
        <CategoryRows key={`${group.cost_center}-${category.category}`} category={category} matrix={matrix} cellClass={cellClass} />
      ))}

      <tr className={cn('border-b border-surface-3 bg-blue-200/70 font-bold dark:bg-blue-900/50', compact ? 'text-[11px]' : 'text-sm')}>
        <td className={cn('sticky left-0 z-10 bg-blue-200/95 text-foreground dark:bg-blue-900/90', labelColumnClass, cellClass)}>
          {group.cost_center} - Totais
        </td>
        <AmountCells totals={group.subtotal} matrix={matrix} cellClass={cellClass} />
      </tr>
    </>
  )
}

function CategoryRows({
  category,
  matrix,
  cellClass,
}: {
  category: CategoryMatrix['groups'][number]['categories'][number]
  matrix: CategoryMatrix
  cellClass: string
}) {
  return (
    <>
      <tr className="border-b border-surface-3 bg-blue-100/60 font-semibold dark:bg-blue-950/30">
        <td className={cn('sticky left-0 z-10 bg-blue-100/95 text-foreground dark:bg-blue-950/90', labelColumnClass, cellClass)}>
          {category.category} - Totais
        </td>
        <AmountCells totals={category.subtotal} matrix={matrix} cellClass={cellClass} />
      </tr>

      {category.subcategories.map((subcategory) => (
        <SubcategoryRows key={subcategory.subcategory} subcategory={subcategory} matrix={matrix} cellClass={cellClass} />
      ))}
    </>
  )
}

function SubcategoryRows({
  subcategory,
  matrix,
  cellClass,
}: {
  subcategory: CategoryMatrix['groups'][number]['categories'][number]['subcategories'][number]
  matrix: CategoryMatrix
  cellClass: string
}) {
  return (
    <>
      <tr className="border-b border-surface-3 bg-blue-50/80 font-semibold dark:bg-blue-950/20">
        <td className={cn('sticky left-0 z-10 bg-blue-50/95 pl-6 text-foreground dark:bg-blue-950/80', labelColumnClass, cellClass)}>
          {subcategory.subcategory} - Totais
        </td>
        <AmountCells totals={subcategory.subtotal} matrix={matrix} cellClass={cellClass} />
      </tr>
    </>
  )
}

function GrandTotalRow({
  matrix,
  cellClass,
  compact,
}: {
  matrix: CategoryMatrix
  cellClass: string
  compact: boolean
}) {
  return (
    <tr className={cn('border-t-2 border-surface-3 bg-blue-200 font-bold dark:bg-blue-900/60', compact ? 'text-[11px]' : 'text-sm')}>
      <td className={cn('sticky left-0 z-10 bg-blue-200 text-foreground dark:bg-blue-900/90', labelColumnClass, cellClass)}>
        Total geral
      </td>
      <AmountCells totals={matrix.grand_total} matrix={matrix} cellClass={cellClass} highlightAllValues />
    </tr>
  )
}
