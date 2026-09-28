import type { ReactNode } from 'react'
import {
  ArrowLeftRight,
  CreditCard,
  Hash,
  Landmark,
  Layers,
  ListTree,
  Receipt,
  Repeat2,
  Split,
  StickyNote,
} from 'lucide-react'
import { Badge } from '@/shared/design-system'
import { cn } from '@/shared/utils/cn'
import { formatCurrency, formatDate, formatDateTime } from '@/shared/utils/format'
import type { Account } from '@/shared/types/models'

function DetailItem({
  label,
  children,
  className,
}: {
  label: string
  children: ReactNode
  className?: string
}) {
  return (
    <div className={cn('min-w-0', className)}>
      <dt className="text-[12px] font-medium tracking-wide text-muted uppercase">{label}</dt>
      <dd className="mt-1 min-w-0 truncate text-sm text-foreground">{children}</dd>
    </div>
  )
}

function SectionTitle({ children }: { children: ReactNode }) {
  return <h3 className="text-sm font-semibold text-foreground">{children}</h3>
}

function CategoryValue({ category }: { category: NonNullable<Account['category']> }) {
  return (
    <span className="inline-flex items-center gap-1.5">
      <span
        aria-hidden="true"
        className="size-2.5 shrink-0 rounded-full"
        style={{ backgroundColor: category.color ?? '#e2e8f0' }}
      />
      <span className="truncate">{category.name}</span>
    </span>
  )
}

export function AccountDetailPanel({ account }: { account: Account }) {
  const allocations = account.allocations ?? []
  const settlements = account.settlements ?? []
  const settledPercent =
    account.value > 0 ? Math.min(Math.round((account.settled_amount / account.value) * 100), 100) : 0
  const counterpartyLabel = account.type === 'receivable' ? 'Cliente' : 'Fornecedor'
  const isCancelled = account.status === 'cancelled'

  return (
    <div className="bg-surface-2/30 px-5 py-6">
      <div className="space-y-6">
        <div className="flex flex-wrap items-center gap-2">
          <Badge variant={account.type === 'receivable' ? 'success' : 'warning'}>{account.type_label}</Badge>
          {account.installment_number !== null && (
            <Badge variant="neutral">
              <Hash className="size-3" />
              Parcela {account.installment_number}/{account.installment_total}
            </Badge>
          )}
          {account.allocation_mode === 'split' && (
            <Badge variant="primary">
              <Split className="size-3" />
              Rateio
            </Badge>
          )}
          {account.is_card_purchase && (
            <Badge variant="neutral">
              <CreditCard className="size-3" />
              Compra no cartão
            </Badge>
          )}
          {account.is_card_invoice_payable && (
            <Badge variant="neutral">
              <Receipt className="size-3" />
              Fatura de cartão
            </Badge>
          )}
          {account.is_reconciled && <Badge variant="success">Conciliado</Badge>}
          <span className="text-[13px] text-muted">
            {counterpartyLabel}: {account.counterparty || '—'}
          </span>
        </div>

        <div className="grid gap-4 rounded-lg border border-surface-2 bg-surface p-4 sm:grid-cols-2 lg:grid-cols-4">
          <div>
            <p className="text-[13px] text-muted">Valor total</p>
            <p className={cn('font-semibold', account.type === 'receivable' ? 'text-success' : 'text-foreground')}>
              {formatCurrency(account.value)}
            </p>
          </div>
          <div>
            <p className="text-[13px] text-muted">Liquidado</p>
            <p className="font-semibold text-foreground">{formatCurrency(account.settled_amount)}</p>
          </div>
          <div>
            <p className="text-[13px] text-muted">Restante</p>
            <p className="font-semibold text-foreground">{formatCurrency(account.remaining_amount)}</p>
          </div>
          <div className="min-w-0">
            <div className="flex items-center justify-between gap-2">
              <p className="text-[13px] text-muted">Progresso</p>
              <p className="text-[13px] font-medium text-foreground">{settledPercent}%</p>
            </div>
            <div
              role="progressbar"
              aria-valuenow={settledPercent}
              aria-valuemin={0}
              aria-valuemax={100}
              className="mt-2 h-1.5 w-full overflow-hidden rounded-full bg-surface-3"
            >
              <div
                className={cn('h-full rounded-full', isCancelled ? 'bg-muted' : 'bg-success')}
                style={{ width: `${settledPercent}%` }}
              />
            </div>
            <p className="mt-1.5 text-[13px] text-muted">
              {settlements.length > 0
                ? `${settlements.length} ${settlements.length === 1 ? 'baixa registrada' : 'baixas registradas'}`
                : 'Nenhuma baixa registrada'}
            </p>
          </div>
        </div>

        <div className="space-y-3">
          <SectionTitle>Detalhes</SectionTitle>
          <dl className="grid gap-x-6 gap-y-4 sm:grid-cols-2 lg:grid-cols-3">
            <DetailItem label="Categoria">
              {account.allocation_mode === 'split' ? (
                <span className="inline-flex items-center gap-1.5">
                  <Split className="size-3.5 text-muted" />
                  Rateio entre {allocations.length} {allocations.length === 1 ? 'fatia' : 'fatias'}
                </span>
              ) : account.category ? (
                <CategoryValue category={account.category} />
              ) : (
                '—'
              )}
            </DetailItem>
            <DetailItem label="Subcategoria">
              {account.allocation_mode === 'split' ? '—' : account.subcategory?.name || '—'}
            </DetailItem>
            <DetailItem label="Centro de custo">
              <span className="inline-flex items-center gap-1.5">
                <Layers className="size-3.5 shrink-0 text-muted" />
                <span className="truncate">{account.cost_center || '—'}</span>
              </span>
            </DetailItem>
            <DetailItem label="Conta bancária">
              <span className="inline-flex items-center gap-1.5">
                <Landmark className="size-3.5 shrink-0 text-muted" />
                <span className="truncate">{account.bank_account || '—'}</span>
              </span>
            </DetailItem>
            <DetailItem label="Cartão de crédito">
              <span className="inline-flex items-center gap-1.5">
                <CreditCard className="size-3.5 shrink-0 text-muted" />
                <span className="truncate">{account.credit_card || '—'}</span>
              </span>
            </DetailItem>
            <DetailItem label="Grupo de parcelas">
              <span className="inline-flex items-center gap-1.5">
                <ListTree className="size-3.5 shrink-0 text-muted" />
                <span className="truncate font-mono text-[13px]">{account.installment_group_id || '—'}</span>
              </span>
            </DetailItem>
            <DetailItem label="Recorrência">
              <span className="inline-flex items-center gap-1.5">
                <Repeat2 className="size-3.5 shrink-0 text-muted" />
                <span className="inline-flex items-center gap-2">
                  {account.recurrence_id ? 'Vinculada' : '—'}
                  {account.recurrence_id && (
                    <span className="font-mono text-[12px] text-muted">{account.recurrence_id}</span>
                  )}
                </span>
              </span>
            </DetailItem>
            <DetailItem label="Transferência">
              <span className="inline-flex items-center gap-1.5">
                <ArrowLeftRight className="size-3.5 shrink-0 text-muted" />
                {account.transfer_id !== null ? `#${account.transfer_id}` : '—'}
              </span>
            </DetailItem>
            <DetailItem label="Identificador">
              <span className="font-mono text-[13px]">{account.id}</span>
            </DetailItem>
          </dl>
        </div>

        <div className="space-y-3">
          <SectionTitle>Datas</SectionTitle>
          <dl className="grid gap-x-6 gap-y-4 sm:grid-cols-2 lg:grid-cols-3">
            <DetailItem label="Data da compra">{formatDate(account.purchase_date)}</DetailItem>
            <DetailItem label="Vencimento">{formatDate(account.due_date)}</DetailItem>
            <DetailItem label="Data prevista">{formatDate(account.expected_date)}</DetailItem>
            <DetailItem label="Data da baixa">{formatDate(account.paid_date)}</DetailItem>
            <DetailItem label="Criado em">{formatDateTime(account.created_at)}</DetailItem>
            <DetailItem label="Atualizado em">{formatDateTime(account.updated_at)}</DetailItem>
          </dl>
        </div>

        {account.allocation_mode === 'split' && allocations.length > 0 && (
          <div className="space-y-3">
            <SectionTitle>Rateio</SectionTitle>
            <div className="overflow-x-auto rounded-lg border border-surface-2 bg-surface">
              <table className="min-w-full text-sm">
                <thead>
                  <tr className="bg-surface-2/60 text-left text-xs tracking-wide text-muted uppercase">
                    <th className="px-4 py-2.5 font-medium">Categoria</th>
                    <th className="px-4 py-2.5 font-medium">Subcategoria</th>
                    <th className="px-4 py-2.5 font-medium">Centro de custo</th>
                    <th className="px-4 py-2.5 text-right font-medium">Valor</th>
                    <th className="px-4 py-2.5 text-right font-medium">%</th>
                  </tr>
                </thead>
                <tbody>
                  {allocations.map((line) => (
                    <tr key={line.id} className="shadow-[inset_0_1px_0_var(--app-surface-2)]">
                      <td className="px-4 py-2.5">
                        {line.category ? <CategoryValue category={line.category} /> : '—'}
                      </td>
                      <td className="px-4 py-2.5 text-muted">{line.subcategory?.name || '—'}</td>
                      <td className="px-4 py-2.5 text-muted">{line.cost_center || '—'}</td>
                      <td className="px-4 py-2.5 text-right tabular-nums">{formatCurrency(line.value)}</td>
                      <td className="px-4 py-2.5 text-right tabular-nums text-muted">
                        {line.percentage !== null ? `${line.percentage.toFixed(1)}%` : '—'}
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          </div>
        )}

        {settlements.length > 0 && (
          <div className="space-y-3">
            <SectionTitle>Baixas</SectionTitle>
            <div className="overflow-x-auto rounded-lg border border-surface-2 bg-surface">
              <table className="min-w-full text-sm">
                <thead>
                  <tr className="bg-surface-2/60 text-left text-xs tracking-wide text-muted uppercase">
                    <th className="px-4 py-2.5 font-medium">Data</th>
                    <th className="px-4 py-2.5 font-medium">Método</th>
                    <th className="px-4 py-2.5 text-right font-medium">Valor</th>
                  </tr>
                </thead>
                <tbody>
                  {settlements.map((settlement) => (
                    <tr key={settlement.id} className="shadow-[inset_0_1px_0_var(--app-surface-2)]">
                      <td className="px-4 py-2.5 text-muted">{formatDate(settlement.settled_at)}</td>
                      <td className="px-4 py-2.5 text-muted">{settlement.method || '—'}</td>
                      <td className="px-4 py-2.5 text-right tabular-nums">{formatCurrency(settlement.value)}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          </div>
        )}

        {account.observation && (
          <div className="space-y-3">
            <SectionTitle>Observações</SectionTitle>
            <div className="flex gap-2.5 rounded-lg border border-surface-2 bg-surface p-4 text-sm whitespace-pre-wrap text-foreground">
              <StickyNote className="mt-0.5 size-4 shrink-0 text-muted" />
              <p className="min-w-0">{account.observation}</p>
            </div>
          </div>
        )}
      </div>
    </div>
  )
}
