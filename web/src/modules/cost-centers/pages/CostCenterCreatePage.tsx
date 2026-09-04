import { useNavigate } from 'react-router'
import { Page, PageContent, PageHeader } from '@/shared/design-system'
import { CostCenterForm } from '../forms/CostCenterForm'
import { useCreateCostCenter } from '../hooks/useCostCenters'

export default function CostCenterCreatePage() {
  const navigate = useNavigate()
  const create = useCreateCostCenter()

  return (
    <Page>
      <PageHeader
        title="Novo centro de custo"
        description="Cadastre uma conta bancária operacional."
        breadcrumb={[
          { label: 'Dashboard', to: '/dashboard' },
          { label: 'Centros de custo', to: '/cost-centers' },
          { label: 'Novo' },
        ]}
      />
      <PageContent>
        <CostCenterForm
          mode="create"
          submitting={create.isPending}
          onSubmit={async (payload) => {
            await create.mutateAsync(payload)
            navigate('/cost-centers')
          }}
        />
      </PageContent>
    </Page>
  )
}
