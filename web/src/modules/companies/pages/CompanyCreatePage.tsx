import { useNavigate } from 'react-router'
import { Page, PageContent, PageHeader } from '@/shared/design-system'
import { CompanyForm } from '../forms/CompanyForm'
import { useCreateCompany } from '../hooks/useCompanies'

export default function CompanyCreatePage() {
  const navigate = useNavigate()
  const create = useCreateCompany()

  return (
    <Page>
      <PageHeader
        title="Nova empresa"
        description="Cadastre uma empresa."
        breadcrumb={[
          { label: 'Dashboard', to: '/dashboard' },
          { label: 'Empresas', to: '/companies' },
          { label: 'Nova' },
        ]}
      />
      <PageContent>
        <CompanyForm
          mode="create"
          submitting={create.isPending}
          onSubmit={async (payload) => {
            await create.mutateAsync(payload)
            navigate('/companies')
          }}
        />
      </PageContent>
    </Page>
  )
}
