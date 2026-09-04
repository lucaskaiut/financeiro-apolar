import { useNavigate, useParams } from 'react-router'
import { Building2 } from 'lucide-react'
import {
  ButtonLink,
  Card,
  EmptyState,
  Page,
  PageContent,
  PageHeader,
  Skeleton,
} from '@/shared/design-system'
import { CompanyForm } from '../forms/CompanyForm'
import { useCompanyQuery, useUpdateCompany } from '../hooks/useCompanies'

export default function CompanyEditPage() {
  const { id } = useParams<{ id: string }>()
  const navigate = useNavigate()

  const query = useCompanyQuery(id)
  const update = useUpdateCompany(id ?? '')

  return (
    <Page>
      <PageHeader
        title="Editar empresa"
        breadcrumb={[
          { label: 'Dashboard', to: '/dashboard' },
          { label: 'Empresas', to: '/companies' },
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
              icon={Building2}
              title="Empresa não encontrada"
              action={<ButtonLink to="/companies" variant="secondary">Voltar</ButtonLink>}
            />
          </Card>
        )}

        {query.data && (
          <CompanyForm
            mode="edit"
            defaultValues={{
              name: query.data.name,
              status: query.data.status,
            }}
            submitting={update.isPending}
            onSubmit={async (payload) => {
              await update.mutateAsync(payload)
              navigate('/companies')
            }}
          />
        )}
      </PageContent>
    </Page>
  )
}
