import { useNavigate, useParams } from 'react-router'
import { Tags } from 'lucide-react'
import { ButtonLink, Card, EmptyState, Page, PageContent, PageHeader, Skeleton } from '@/shared/design-system'
import { CategoryForm } from '../forms/CategoryForm'
import { useCategoryQuery, useUpdateCategory } from '../hooks/useCategories'

export default function CategoryEditPage() {
  const { id } = useParams<{ id: string }>()
  const navigate = useNavigate()

  const query = useCategoryQuery(id)
  const update = useUpdateCategory(id ?? '')

  return (
    <Page>
      <PageHeader
        title="Editar categoria"
        breadcrumb={[
          { label: 'Dashboard', to: '/dashboard' },
          { label: 'Categorias', to: '/categories' },
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
            <EmptyState icon={Tags} title="Categoria não encontrada" action={<ButtonLink to="/categories" variant="secondary">Voltar</ButtonLink>} />
          </Card>
        )}

        {query.data && (
          <CategoryForm
            mode="edit"
            defaultValues={{
              name: query.data.name,
              type: query.data.type,
              color: query.data.color ?? '',
              status: query.data.status,
            }}
            submitting={update.isPending}
            onSubmit={async (payload) => {
              await update.mutateAsync(payload)
              navigate('/categories')
            }}
          />
        )}
      </PageContent>
    </Page>
  )
}
