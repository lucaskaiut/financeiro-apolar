import { useNavigate, useParams } from 'react-router'
import { Copy, Repeat } from 'lucide-react'
import { Button, ButtonLink, Card, EmptyState, Page, PageContent, PageHeader, Skeleton } from '@/shared/design-system'
import { Can } from '@/app/guards/PermissionGuard'
import { Permission } from '@/shared/constants/permissions'
import { RecurrenceForm } from '../forms/RecurrenceForm'
import { useRecurrenceQuery, useUpdateRecurrence } from '../hooks/useRecurrences'

export default function RecurrenceEditPage() {
  const { id } = useParams<{ id: string }>()
  const navigate = useNavigate()

  const query = useRecurrenceQuery(id)
  const update = useUpdateRecurrence(id ?? '')

  return (
    <Page>
      <PageHeader
        title="Editar recorrência"
        breadcrumb={[
          { label: 'Dashboard', to: '/dashboard' },
          { label: 'Recorrências', to: '/recurrences' },
          { label: 'Editar' },
        ]}
        actions={
          query.data ? (
            <Can permission={Permission.RECURRENCES_CREATE}>
              <Button variant="secondary" onClick={() => navigate(`/recurrences/create?clone=${query.data!.id}`)}>
                <Copy className="size-4" />
                Clonar
              </Button>
            </Can>
          ) : undefined
        }
      />
      <PageContent>
        {query.isPending && (
          <Card>
            <Skeleton className="h-96 w-full" />
          </Card>
        )}

        {query.isError && (
          <Card>
            <EmptyState icon={Repeat} title="Recorrência não encontrada" action={<ButtonLink to="/recurrences" variant="secondary">Voltar</ButtonLink>} />
          </Card>
        )}

        {query.data && (
          <RecurrenceForm
            mode="edit"
            defaultValues={{
              type: query.data.type as 'payable' | 'receivable',
              description: query.data.description,
              counterparty: query.data.counterparty ?? '',
              cost_center_id: query.data.cost_center_id ?? '',
              category_id: query.data.category_id ?? '',
              subcategory_id: query.data.subcategory_id ?? '',
              value: String(query.data.value),
              frequency: query.data.frequency,
              start_date: query.data.start_date,
              end_date: query.data.end_date ?? '',
              max_occurrences: query.data.max_occurrences ? String(query.data.max_occurrences) : '',
              day_of_month: query.data.day_of_month ? String(query.data.day_of_month) : '',
              scope: 'all',
            }}
            submitting={update.isPending}
            onSubmit={async (payload) => {
              await update.mutateAsync(payload)
              navigate('/recurrences')
            }}
          />
        )}
      </PageContent>
    </Page>
  )
}
