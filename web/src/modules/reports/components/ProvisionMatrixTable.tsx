import { cn } from '@/shared/utils/cn'
import type { ProvisionReport } from '../services/reports.service'
import { formatProvisionAmount, provisionAccountAmountClass, provisionTotalAmountClass } from '../utils/provision-format'

interface ProvisionMatrixTableProps {
  data: ProvisionReport
  compact?: boolean
  className?: string
}

const labelColumnClass = 'min-w-56 whitespace-nowrap'
const amountColumnClass = 'min-w-[5.75rem] whitespace-nowrap'

export function ProvisionMatrixTable({ data, compact = false, className }: ProvisionMatrixTableProps) {
  const cellClass = compact ? 'px-2 py-1 text-[11px]' : 'px-3 py-2 text-sm'
  const headerClass = compact ? 'px-2 py-1.5 text-[10px]' : 'px-3 py-2 text-xs'
  const columnCount = data.columns.length + 2

  return (
    <div className={cn('overflow-x-auto', className)}>
      <table className="min-w-full border-collapse">

        <tbody>
          {data.groups.map((group, groupIndex) => (
            <CostCenterSection
              key={group.cost_center}
              group={group}
              columns={data.columns}
              columnCount={columnCount}
              cellClass={cellClass}
              headerClass={headerClass}
              compact={compact}
              withTopSpacing={groupIndex > 0}
            />
          ))}

          <SectionBanner label="Total geral" columnCount={columnCount} compact={compact} withTopSpacing />
          <ColumnHeaderRow columns={data.columns} headerClass={headerClass} />
          <GrandTotalRow grandTotal={data.grand_total} columns={data.columns} cellClass={cellClass} compact={compact} />
        </tbody>
      </table>
    </div>
  )
}

function SectionBanner({
  label,
  columnCount,
  compact,
  withTopSpacing = false,
}: {
  label: string
  columnCount: number
  compact: boolean
  withTopSpacing?: boolean
}) {
  return (
    <tr className={withTopSpacing ? 'border-t-8 border-transparent' : undefined}>
      <td
        colSpan={columnCount}
        className={cn(
          'bg-surface-2 px-4 py-3 font-bold uppercase tracking-wide text-foreground',
          compact ? 'text-xs' : 'text-sm',
        )}
      >
        {label}
      </td>
    </tr>
  )
}

function ColumnHeaderRow({
  columns,
  headerClass,
}: {
  columns: ProvisionReport['columns']
  headerClass: string
}) {
  return (
    <tr className="border-b border-surface-3 bg-surface-2/50 text-left uppercase tracking-wide text-muted">
      <th className={cn('sticky left-0 z-20 bg-surface-2/95 text-left font-semibold', labelColumnClass, headerClass)}>
        Conta
      </th>
      {columns.map((column) => (
        <th key={column.key} className={cn('whitespace-nowrap text-right font-semibold', amountColumnClass, headerClass)}>
          {column.label}
        </th>
      ))}
      <th className={cn('whitespace-nowrap text-right font-semibold', amountColumnClass, headerClass)}>Total</th>
    </tr>
  )
}

function AmountCells({
  amounts,
  columns,
  cellClass,
  valueClass,
  total,
}: {
  amounts: Record<string, number | null>
  columns: ProvisionReport['columns']
  cellClass: string
  valueClass: (value: number | null | undefined) => string
  total?: number | null
}) {
  return (
    <>
      {columns.map((column) => {
        const value = amounts[column.key]

        return (
          <td key={column.key} className={cn('text-right tabular-nums', amountColumnClass, cellClass, valueClass(value))}>
            {formatProvisionAmount(value)}
          </td>
        )
      })}
      {total !== undefined && (
        <td className={cn('text-right tabular-nums', amountColumnClass, cellClass, valueClass(total))}>
          {formatProvisionAmount(total)}
        </td>
      )}
    </>
  )
}

function CostCenterSection({
  group,
  columns,
  columnCount,
  cellClass,
  headerClass,
  compact,
  withTopSpacing,
}: {
  group: ProvisionReport['groups'][number]
  columns: ProvisionReport['columns']
  columnCount: number
  cellClass: string
  headerClass: string
  compact: boolean
  withTopSpacing: boolean
}) {
  return (
    <>
      <SectionBanner label={group.cost_center} columnCount={columnCount} compact={compact} withTopSpacing={withTopSpacing} />
      <ColumnHeaderRow columns={columns} headerClass={headerClass} />

      {group.rows.map((row) => (
        <tr key={row.account_id} className="border-b border-surface-3/60">
          <td className={cn('sticky left-0 z-10 bg-background font-medium text-foreground', labelColumnClass, cellClass)}>
            {row.description}
          </td>
          <AmountCells
            amounts={row.amounts}
            columns={columns}
            cellClass={cellClass}
            valueClass={provisionAccountAmountClass}
          />
          <td className={cellClass} />
        </tr>
      ))}

      <tr className={cn('border-b border-surface-3 bg-surface-2/70 font-semibold', compact ? 'text-[11px]' : 'text-sm')}>
        <td className={cn('sticky left-0 z-10 bg-surface-2/95 text-foreground', labelColumnClass, cellClass)}>Subtotal</td>
        <AmountCells
          amounts={group.subtotal.amounts}
          columns={columns}
          cellClass={cellClass}
          valueClass={provisionTotalAmountClass}
          total={group.subtotal.total}
        />
      </tr>
    </>
  )
}

function GrandTotalRow({
  grandTotal,
  columns,
  cellClass,
  compact,
}: {
  grandTotal: ProvisionReport['grand_total']
  columns: ProvisionReport['columns']
  cellClass: string
  compact: boolean
}) {
  return (
    <tr className={cn('border-t-2 border-surface-3 bg-surface-2 font-bold', compact ? 'text-[11px]' : 'text-sm')}>
      <td className={cn('sticky left-0 z-10 bg-surface-2 text-foreground', labelColumnClass, cellClass)}>Total geral</td>
      <AmountCells
        amounts={grandTotal.amounts}
        columns={columns}
        cellClass={cellClass}
        valueClass={provisionTotalAmountClass}
        total={grandTotal.total}
      />
    </tr>
  )
}
