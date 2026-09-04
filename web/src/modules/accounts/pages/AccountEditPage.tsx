import { useState } from 'react'
import { useNavigate, useParams } from 'react-router'
import { Banknote, Copy, RotateCcw } from 'lucide-react'
import { Button, ButtonLink, Card, ConfirmDialog, EmptyState, Page, PageContent, PageHeader, Skeleton } from '@/shared/design-system'
import { Can } from '@/app/guards/PermissionGuard'
import { Permission } from '@/shared/constants/permissions'
import { formatCurrency } from '@/shared/utils/format'
import { AccountForm } from '../forms/AccountForm'
import { DocumentsSection } from '../components/DocumentsSection'
import { useAccountQuery, useReopenAccount, useUpdateAccount } from '../hooks/useAccounts'

export default function AccountEditPage() {
  const { id } = useParams<{ id: string }>()
  const navigate = useNavigate()
  const [reopenOpen, setReopenOpen] = useState(false)

  const query = useAccountQuery(id)
  const update = useUpdateAccount(id ?? '')
  const reopen = useReopenAccount(id ?? '')

  const hasSettlement = (query.data?.settled_amount ?? 0) > 0 || (query.data?.settlements?.length ?? 0) > 0
  const canReopen = hasSettlement && query.data?.status !== 'cancelled'

  return (
    <Page>
      <PageHeader
        title="Editar lançamento"
        breadcrumb={[
          { label: 'Dashboard', to: '/dashboard' },
          { label: 'Contas', to: '/accounts' },
          { label: 'Editar' },
        ]}
        actions={
          query.data ? (
            <Can permission={Permission.ACCOUNTS_CREATE}>
              <Button variant="secondary" onClick={() => navigate(`/accounts/create?clone=${query.data!.id}`)}>
                <Copy className="size-4" />
                Clonar
              </Button>
            </Can>
          ) : undefined
        }
      />
      <PageContent>
        {query.isPending && (
          <Card>
            <Skeleton className="h-96 w-full" />
          </Card>
        )}

        {query.isError && (
          <Card>
            <EmptyState icon={Banknote} title="Lançamento não encontrado" action={<ButtonLink to="/accounts" variant="secondary">Voltar</ButtonLink>} />
          </Card>
        )}

        {query.data && (
          <>
            {canReopen && (
              <Card className="mb-4">
                <div className="flex flex-col gap-4 p-4 sm:flex-row sm:items-center sm:justify-between">
                  <div>
                    <p className="text-sm font-medium text-foreground">Conta com baixa registrada</p>
                    <p className="mt-1 text-[13px] text-muted">
                      Valor baixado: {formatCurrency(query.data.settled_amount)}
                      {query.data.is_reconciled ? ' · Conciliada com extrato bancário' : ''}
                    </p>
                  </div>

                  <Can permission={Permission.ACCOUNTS_SETTLE}>
                    <Button variant="secondary" onClick={() => setReopenOpen(true)} className="shrink-0">
                      <RotateCcw className="size-4" />
                      Reabrir conta
                    </Button>
                  </Can>
                </div>
              </Card>
            )}

            <AccountForm
              mode="edit"
              defaultValues={{
                type: query.data.type,
                description: query.data.description,
                counterparty: query.data.counterparty ?? '',
                cost_center_id: query.data.cost_center_id ?? '',
                category_id: query.data.category_id ?? '',
                subcategory_id: query.data.subcategory_id ?? '',
                value: String(query.data.value),
                due_date: query.data.due_date ?? '',
                expected_date: query.data.expected_date ?? '',
                paid_date: query.data.paid_date ?? query.data.settlements?.at(-1)?.settled_at ?? '',
                observation: query.data.observation ?? '',
                installments: false,
                installment_quantity: '2',
                installment_interval: 'monthly',
              }}
              submitting={update.isPending}
              hasSettlement={hasSettlement}
              onSubmit={async (payload) => {
                await update.mutateAsync(payload)
                navigate('/accounts')
              }}
            />

            <DocumentsSection accountId={query.data.id} />

            <ConfirmDialog
              open={reopenOpen}
              onClose={() => setReopenOpen(false)}
              onConfirm={() => {
                reopen.mutate(undefined, {
                  onSuccess: () => setReopenOpen(false),
                })
              }}
              loading={reopen.isPending}
              title="Reabrir conta"
              confirmLabel="Sim, reabrir conta"
              variant="danger"
              description={
                <div className="space-y-3 text-sm text-muted">
                  <p>
                    Você está prestes a <strong className="text-foreground">reabrir</strong> o lançamento{' '}
                    <strong className="text-foreground">{query.data.description}</strong>.
                  </p>
                  <p>Esta ação irá:</p>
                  <ul className="list-disc space-y-1 pl-5">
                    <li>remover todas as baixas registradas ({formatCurrency(query.data.settled_amount)})</li>
                    <li>retirar o lançamento do fluxo de caixa realizado</li>
                    <li>limpar a data da baixa e voltar o status para em aberto</li>
                    {query.data.is_reconciled && (
                      <li>desfazer a conciliação bancária vinculada e reabrir a transação no extrato</li>
                    )}
                  </ul>
                  <p className="font-medium text-foreground">Esta operação não pode ser desfeita automaticamente.</p>
                </div>
              }
            />
          </>
        )}
      </PageContent>
    </Page>
  )
}
