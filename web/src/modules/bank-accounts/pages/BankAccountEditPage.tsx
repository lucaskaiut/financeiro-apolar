import { useNavigate, useParams } from 'react-router'
import { Landmark } from 'lucide-react'
import {
  ButtonLink,
  Card,
  EmptyState,
  Page,
  PageContent,
  PageHeader,
  Skeleton,
} from '@/shared/design-system'
import { BankAccountForm } from '../forms/BankAccountForm'
import { useBankAccountQuery, useUpdateBankAccount } from '../hooks/useBankAccounts'

export default function BankAccountEditPage() {
  const { id } = useParams<{ id: string }>()
  const navigate = useNavigate()

  const query = useBankAccountQuery(id)
  const update = useUpdateBankAccount(id ?? '')

  return (
    <Page>
      <PageHeader
        title="Editar conta bancária"
        breadcrumb={[
          { label: 'Dashboard', to: '/dashboard' },
          { label: 'Contas bancárias', to: '/bank-accounts' },
          { label: 'Editar' },
        ]}
      />

      <PageContent>
        {query.isPending && (
          <Card>
            <Skeleton className="h-64 w-full" />
          </Card>
        )}

        {query.isError && (
          <Card>
            <EmptyState
              icon={Landmark}
              title="Conta bancária não encontrada"
              action={<ButtonLink to="/bank-accounts" variant="secondary">Voltar</ButtonLink>}
            />
          </Card>
        )}

        {query.data && (
          <BankAccountForm
            mode="edit"
            defaultValues={{
              name: query.data.name,
              bank: query.data.bank ?? '',
              agency: query.data.agency ?? '',
              account: query.data.account ?? '',
              type: query.data.type,
              initial_balance: String(query.data.initial_balance),
              status: query.data.status,
            }}
            submitting={update.isPending}
            onSubmit={async (payload) => {
              await update.mutateAsync(payload)
              navigate('/bank-accounts')
            }}
          />
        )}
      </PageContent>
    </Page>
  )
}
