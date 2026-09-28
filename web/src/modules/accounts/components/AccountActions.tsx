import { useNavigate } from 'react-router'
import { CheckCircle2, Copy, Pencil, Trash2, Undo2, XCircle } from 'lucide-react'
import { Button, Tooltip } from '@/shared/design-system'
import { Permission } from '@/shared/constants/permissions'
import { usePermissions } from '@/shared/hooks/usePermissions'
import { cn } from '@/shared/utils/cn'
import type { Account } from '@/shared/types/models'
import { useUnsettleAccount } from '../hooks/useAccounts'

export function AccountActions({
  account,
  onSettle,
  onCancel,
  onDelete,
  className,
}: {
  account: Account
  onSettle: (account: Account) => void
  onCancel: (account: Account) => void
  onDelete: (account: Account) => void
  className?: string
}) {
  const navigate = useNavigate()
  const { can } = usePermissions()
  const unsettle = useUnsettleAccount(account.id)
  const settlements = account.settlements ?? []

  return (
    <div className={cn('flex items-center justify-end gap-1', className)}>
      {account.status === 'settled' && settlements.length > 0 && can(Permission.ACCOUNTS_SETTLE) && (
        <Tooltip label="Desfazer baixa">
          <Button
            variant="ghost"
            size="sm"
            onClick={() => {
              const last = settlements[settlements.length - 1]
              if (last) unsettle.mutate(last.id)
            }}
            aria-label={`Desfazer baixa de ${account.description}`}
            className="text-warning hover:bg-warning-soft hover:text-warning"
          >
            <Undo2 className="size-4" />
          </Button>
        </Tooltip>
      )}
      {(account.status === 'open' || account.status === 'partial') &&
        !account.is_card_purchase &&
        can(Permission.ACCOUNTS_SETTLE) && (
          <Tooltip label="Liquidar">
            <Button
              variant="ghost"
              size="sm"
              onClick={() => onSettle(account)}
              aria-label={`Baixar ${account.description}`}
              className="text-success hover:bg-success-soft hover:text-success"
            >
              <CheckCircle2 className="size-4" />
            </Button>
          </Tooltip>
        )}
      {can(Permission.ACCOUNTS_CREATE) && (
        <Tooltip label="Clonar">
          <Button
            variant="ghost"
            size="sm"
            onClick={() => navigate(`/accounts/create?clone=${account.id}`)}
            aria-label={`Clonar ${account.description}`}
          >
            <Copy className="size-4" />
          </Button>
        </Tooltip>
      )}
      {can(Permission.ACCOUNTS_UPDATE) && (
        <Tooltip label="Editar">
          <Button
            variant="ghost"
            size="sm"
            onClick={() => navigate(`/accounts/${account.id}/edit`)}
            aria-label={`Editar ${account.description}`}
          >
            <Pencil className="size-4" />
          </Button>
        </Tooltip>
      )}
      {can(Permission.ACCOUNTS_UPDATE) && (account.status === 'open' || account.status === 'partial') && (
        <Tooltip label="Cancelar">
          <Button
            variant="ghost"
            size="sm"
            onClick={() => onCancel(account)}
            aria-label={`Cancelar ${account.description}`}
            className="text-warning hover:bg-warning-soft hover:text-warning"
          >
            <XCircle className="size-4" />
          </Button>
        </Tooltip>
      )}
      {can(Permission.ACCOUNTS_DELETE) && (
        <Tooltip label="Excluir">
          <Button
            variant="ghost"
            size="sm"
            onClick={() => onDelete(account)}
            aria-label={`Excluir ${account.description}`}
            className="text-danger hover:bg-danger-soft hover:text-danger"
          >
            <Trash2 className="size-4" />
          </Button>
        </Tooltip>
      )}
    </div>
  )
}
