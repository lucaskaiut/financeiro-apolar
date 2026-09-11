import { Badge, Modal } from '@/shared/design-system'
import { formatCurrency, formatDate } from '@/shared/utils/format'
import type { Account, BankTransaction } from '@/shared/types/models'

export function IdentifyDialog({
  transaction,
  accounts,
  open,
  onClose,
}: {
  transaction: BankTransaction | null
  accounts: Account[]
  open: boolean
  onClose: () => void
}) {
  return (
    <Modal
      open={open}
      onClose={onClose}
      title="Contas identificadas"
      description={
        transaction
          ? `Lançamentos que serão liquidados pela conciliação automática da transação de ${formatCurrency(transaction.value)}.`
          : undefined
      }
      size="lg"
    >
      {accounts.length === 0 ? (
        <p className="text-sm text-muted">Nenhuma conta identificada para esta transação.</p>
      ) : (
        <div className="space-y-2">
          {accounts.map((account) => (
            <div key={account.id} className="flex items-center gap-3 rounded-lg bg-surface-2 p-3">
              <div className="min-w-0 flex-1">
                <p className="truncate text-sm font-medium text-foreground">{account.description}</p>
                <p className="truncate text-[13px] text-muted">
                  {account.bank_account ?? 'Sem conta bancária'} · vencimento {formatDate(account.due_date)}
                </p>
              </div>
              <Badge variant={account.type === 'receivable' ? 'success' : 'warning'}>
                {formatCurrency(account.remaining_amount)}
              </Badge>
            </div>
          ))}
        </div>
      )}
    </Modal>
  )
}
