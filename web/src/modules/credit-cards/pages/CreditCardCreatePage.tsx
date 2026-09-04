import { useNavigate } from 'react-router'
import { Page, PageContent, PageHeader } from '@/shared/design-system'
import { CreditCardForm } from '../forms/CreditCardForm'
import { useCreateCreditCard } from '../hooks/useCreditCards'

export default function CreditCardCreatePage() {
  const navigate = useNavigate()
  const create = useCreateCreditCard()

  return (
    <Page>
      <PageHeader
        title="Novo cartão de crédito"
        description="Cadastre um cartão de crédito."
        breadcrumb={[
          { label: 'Dashboard', to: '/dashboard' },
          { label: 'Cartões de crédito', to: '/credit-cards' },
          { label: 'Novo' },
        ]}
      />
      <PageContent>
        <CreditCardForm
          mode="create"
          submitting={create.isPending}
          onSubmit={async (payload) => {
            await create.mutateAsync(payload)
            navigate('/credit-cards')
          }}
        />
      </PageContent>
    </Page>
  )
}
