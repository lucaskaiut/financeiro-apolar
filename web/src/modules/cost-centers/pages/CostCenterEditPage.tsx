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
import { CostCenterForm } from '../forms/CostCenterForm'
import { useCostCenterQuery, useUpdateCostCenter } from '../hooks/useCostCenters'

export default function CostCenterEditPage() {
  const { id } = useParams<{ id: string }>()
  const navigate = useNavigate()

  const query = useCostCenterQuery(id)
  const update = useUpdateCostCenter(id ?? '')

  return (
    <Page>
      <PageHeader
        title="Editar centro de custo"
        breadcrumb={[
          { label: 'Dashboard', to: '/dashboard' },
          { label: 'Centros de custo', to: '/cost-centers' },
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
              title="Centro de custo não encontrado"
              action={<ButtonLink to="/cost-centers" variant="secondary">Voltar</ButtonLink>}
            />
          </Card>
        )}

        {query.data && (
          <CostCenterForm
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
              navigate('/cost-centers')
            }}
          />
        )}
      </PageContent>
    </Page>
  )
}
