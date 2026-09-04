import { useNavigate } from 'react-router'
import { Page, PageContent, PageHeader } from '@/shared/design-system'
import { BankAccountForm } from '../forms/BankAccountForm'
import { useCreateBankAccount } from '../hooks/useBankAccounts'

export default function BankAccountCreatePage() {
  const navigate = useNavigate()
  const create = useCreateBankAccount()

  return (
    <Page>
      <PageHeader
        title="Nova conta bancária"
        description="Cadastre uma conta bancária operacional."
        breadcrumb={[
          { label: 'Dashboard', to: '/dashboard' },
          { label: 'Contas bancárias', to: '/bank-accounts' },
          { label: 'Nova' },
        ]}
      />
      <PageContent>
        <BankAccountForm
          mode="create"
          submitting={create.isPending}
          onSubmit={async (payload) => {
            await create.mutateAsync(payload)
            navigate('/bank-accounts')
          }}
        />
      </PageContent>
    </Page>
  )
}
