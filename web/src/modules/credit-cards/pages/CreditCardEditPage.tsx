import { useNavigate, useParams } from 'react-router'
import { CreditCard } from 'lucide-react'
import {
  ButtonLink,
  Card,
  EmptyState,
  Page,
  PageContent,
  PageHeader,
  Skeleton,
} from '@/shared/design-system'
import { CreditCardForm } from '../forms/CreditCardForm'
import { useCreditCardQuery, useUpdateCreditCard } from '../hooks/useCreditCards'

export default function CreditCardEditPage() {
  const { id } = useParams<{ id: string }>()
  const navigate = useNavigate()

  const query = useCreditCardQuery(id)
  const update = useUpdateCreditCard(id ?? '')

  return (
    <Page>
      <PageHeader
        title="Editar cartão de crédito"
        breadcrumb={[
          { label: 'Dashboard', to: '/dashboard' },
          { label: 'Cartões de crédito', to: '/credit-cards' },
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
              icon={CreditCard}
              title="Cartão não encontrado"
              action={<ButtonLink to="/credit-cards" variant="secondary">Voltar</ButtonLink>}
            />
          </Card>
        )}

        {query.data && (
          <CreditCardForm
            mode="edit"
            defaultValues={{
              name: query.data.name,
              institution: query.data.institution ?? '',
              limit: query.data.limit != null ? String(query.data.limit) : '',
              closing_day: query.data.closing_day != null ? String(query.data.closing_day) : '',
              due_day: query.data.due_day != null ? String(query.data.due_day) : '',
              bank_account_id: query.data.bank_account_id ?? '',
              status: query.data.status,
            }}
            submitting={update.isPending}
            onSubmit={async (payload) => {
              await update.mutateAsync(payload)
              navigate(`/credit-cards/${id}`)
            }}
          />
        )}
      </PageContent>
    </Page>
  )
}
