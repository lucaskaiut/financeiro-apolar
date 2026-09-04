import { cn } from '@/shared/utils/cn'
import type { MonthlySummaryReport } from '../services/reports.service'
import { categoryAmountClass, categoryTotalAmountClass, formatCategoryAmount } from '../utils/category-format'

interface MonthlySummaryTableProps {
  data: MonthlySummaryReport
  compact?: boolean
  className?: string
}

const labelColumnClass = 'min-w-56 w-56 whitespace-nowrap'

export function MonthlySummaryTable({ data, compact = false, className }: MonthlySummaryTableProps) {
  const cellClass = compact ? 'px-2 py-1 text-[11px]' : 'px-3 py-2 text-sm'
  const headerClass = compact ? 'px-2 py-1.5 text-[10px]' : 'px-3 py-2 text-xs'

  return (
    <div className={cn('space-y-3', className)}>
      <div className="overflow-x-auto">
        <table className="min-w-full border-collapse">
          <thead>
            <tr className="border-b border-surface-3 bg-blue-100/80 dark:bg-blue-950/40">
              <th
                className={cn(
                  'sticky left-0 z-20 bg-blue-100/95 text-left font-semibold tracking-wide text-foreground uppercase dark:bg-blue-950/90',
                  labelColumnClass,
                  headerClass,
                )}
              >
                Centro de custo
              </th>
              {data.columns.map((column) => (
                <th
                  key={column.key}
                  className={cn(
                    'whitespace-nowrap text-right font-semibold tracking-wide text-foreground uppercase',
                    headerClass,
                  )}
                >
                  {column.label}
                </th>
              ))}
              <th className={cn('whitespace-nowrap text-right font-semibold tracking-wide text-foreground uppercase', headerClass)}>
                Total
              </th>
            </tr>
          </thead>
          <tbody>
            {data.rows.map((row) => (
              <tr key={row.cost_center_id ?? row.cost_center} className="border-b border-surface-3/60">
                <td className={cn('sticky left-0 z-10 bg-background font-semibold text-foreground', labelColumnClass, cellClass)}>
                  {row.cost_center}
                </td>
                {data.columns.map((column) => {
                  const value = row.amounts[column.key]

                  return (
                    <td key={column.key} className={cn('whitespace-nowrap text-right tabular-nums', cellClass, categoryAmountClass(value))}>
                      {formatCategoryAmount(value)}
                    </td>
                  )
                })}
                <td className={cn('whitespace-nowrap text-right tabular-nums font-medium', cellClass, categoryAmountClass(row.total))}>
                  {formatCategoryAmount(row.total)}
                </td>
              </tr>
            ))}

            <tr className="border-t-2 border-surface-3 bg-blue-100/80 font-bold dark:bg-blue-950/40">
              <td className={cn('sticky left-0 z-10 bg-blue-100/95 text-foreground dark:bg-blue-950/90', labelColumnClass, cellClass)}>
                Total
              </td>
              {data.columns.map((column) => {
                const value = data.grand_total.amounts[column.key]

                return (
                  <td key={column.key} className={cn('whitespace-nowrap text-right tabular-nums', cellClass, categoryTotalAmountClass(value))}>
                    {formatCategoryAmount(value)}
                  </td>
                )
              })}
              <td className={cn('whitespace-nowrap text-right tabular-nums', cellClass, categoryTotalAmountClass(data.grand_total.total))}>
                {formatCategoryAmount(data.grand_total.total)}
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <div className="overflow-x-auto">
        <table className="min-w-full border-collapse">
          <tbody>
            <tr className="bg-orange-100/90 font-bold dark:bg-orange-950/40">
              <td
                colSpan={data.columns.length + 1}
                className={cn('sticky left-0 z-10 bg-orange-100/95 text-center text-foreground dark:bg-orange-950/90', cellClass)}
              >
                Média mês
              </td>
              <td className={cn('whitespace-nowrap text-right tabular-nums text-foreground', cellClass)}>
                {formatCategoryAmount(data.monthly_average)}
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  )
}
