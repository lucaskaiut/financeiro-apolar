import { useNavigate } from 'react-router'
import { Page, PageContent, PageHeader } from '@/shared/design-system'
import { TransferForm } from '../forms/TransferForm'
import { useCreateTransfer } from '../hooks/useTransfers'

export default function TransferCreatePage() {
  const navigate = useNavigate()
  const create = useCreateTransfer()

  return (
    <Page>
      <PageHeader
        title="Nova transferência"
        description="Transfira valores entre centros de custo."
        breadcrumb={[
          { label: 'Dashboard', to: '/dashboard' },
          { label: 'Transferências', to: '/transfers' },
          { label: 'Nova' },
        ]}
      />
      <PageContent>
        <TransferForm
          submitting={create.isPending}
          onSubmit={async (payload) => {
            await create.mutateAsync(payload)
            navigate('/transfers')
          }}
        />
      </PageContent>
    </Page>
  )
}
