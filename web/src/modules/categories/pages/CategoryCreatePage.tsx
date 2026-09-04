import { useNavigate } from 'react-router'
import { Page, PageContent, PageHeader } from '@/shared/design-system'
import { CategoryForm } from '../forms/CategoryForm'
import { useCreateCategory } from '../hooks/useCategories'

export default function CategoryCreatePage() {
  const navigate = useNavigate()
  const create = useCreateCategory()

  return (
    <Page>
      <PageHeader
        title="Nova categoria"
        description="Crie uma categoria financeira."
        breadcrumb={[
          { label: 'Dashboard', to: '/dashboard' },
          { label: 'Categorias', to: '/categories' },
          { label: 'Nova' },
        ]}
      />
      <PageContent>
        <CategoryForm
          mode="create"
          submitting={create.isPending}
          onSubmit={async (payload) => {
            await create.mutateAsync(payload)
            navigate('/categories')
          }}
        />
      </PageContent>
    </Page>
  )
}
