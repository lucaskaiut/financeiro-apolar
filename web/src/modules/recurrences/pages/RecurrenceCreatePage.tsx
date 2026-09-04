import { useNavigate, useSearchParams } from 'react-router'
import { Card, EmptyState, Page, PageContent, PageHeader, Skeleton } from '@/shared/design-system'
import { Repeat } from 'lucide-react'
import { RecurrenceForm } from '../forms/RecurrenceForm'
import { useCreateRecurrence, useRecurrenceQuery } from '../hooks/useRecurrences'
import { recurrenceToCloneFormValues } from '../utils/clone'

export default function RecurrenceCreatePage() {
  const navigate = useNavigate()
  const [searchParams] = useSearchParams()
  const cloneId = searchParams.get('clone')

  const create = useCreateRecurrence()
  const cloneQuery = useRecurrenceQuery(cloneId ?? undefined)

  const isCloning = Boolean(cloneId)
  const cloneReady = !isCloning || cloneQuery.isSuccess

  return (
    <Page>
      <PageHeader
        title={isCloning ? 'Clonar recorrência' : 'Nova recorrência'}
        description={
          isCloning
            ? 'Os dados foram copiados da recorrência original. Ajuste o que precisar e salve.'
            : 'Crie um lançamento recorrente.'
        }
        breadcrumb={[
          { label: 'Dashboard', to: '/dashboard' },
          { label: 'Recorrências', to: '/recurrences' },
          { label: isCloning ? 'Clonar' : 'Nova' },
        ]}
      />
      <PageContent>
        {isCloning && cloneQuery.isPending && (
          <Card>
            <Skeleton className="h-96 w-full" />
          </Card>
        )}

        {isCloning && cloneQuery.isError && (
          <Card>
            <EmptyState icon={Repeat} title="Recorrência não encontrada" description="Não foi possível carregar a recorrência para clonar." />
          </Card>
        )}

        {cloneReady && (!isCloning || cloneQuery.data) && (
          <RecurrenceForm
            key={isCloning ? `clone-${cloneQuery.data!.id}` : 'new'}
            mode="create"
            defaultValues={cloneQuery.data ? recurrenceToCloneFormValues(cloneQuery.data) : undefined}
            submitting={create.isPending}
            onSubmit={async (payload) => {
              await create.mutateAsync(payload)
              navigate('/recurrences')
            }}
          />
        )}
      </PageContent>
    </Page>
  )
}
