import { useNavigate, useParams } from 'react-router'
import { CalendarRange } from 'lucide-react'
import { ButtonLink, Card, EmptyState, Page, PageContent, PageHeader, Skeleton } from '@/shared/design-system'
import { InstallmentForm } from '../forms/InstallmentForm'
import { useInstallmentQuery, useUpdateInstallment } from '../hooks/useInstallments'

export default function InstallmentEditPage() {
  const { id } = useParams<{ id: string }>()
  const navigate = useNavigate()

  const query = useInstallmentQuery(id)
  const update = useUpdateInstallment(id ?? '')

  const data = query.data

  return (
    <Page>
      <PageHeader
        title="Editar parcelamento"
        breadcrumb={[
          { label: 'Dashboard', to: '/dashboard' },
          { label: 'Parcelamentos', to: '/installments' },
          { label: data?.description ?? 'Editar' },
        ]}
      />

      <PageContent>
        {query.isPending && (
          <Card>
            <Skeleton className="h-96 w-full" />
          </Card>
        )}

        {query.isError && (
          <Card>
            <EmptyState icon={CalendarRange} title="Parcelamento não encontrado" action={<ButtonLink to="/installments" variant="secondary">Voltar</ButtonLink>} />
          </Card>
        )}

        {data && (
          <InstallmentForm
            isCardPurchase={data.is_card_purchase}
            defaultValues={{
              type: data.type,
              description: data.description,
              counterparty: data.counterparty ?? '',
              bank_account_id: data.bank_account_id ?? '',
              company_id: data.company_id ?? '',
              cost_center_id: data.cost_center_id ?? '',
              category_id: data.category_id ?? '',
              subcategory_id: data.subcategory_id ?? '',
              value: String(data.total_value),
              expected_date: data.expected_date ?? '',
              observation: data.observation ?? '',
              scope: 'all',
            }}
            submitting={update.isPending}
            onSubmit={async (payload) => {
              await update.mutateAsync(payload)
              navigate(`/installments/${id}`)
            }}
          />
        )}
      </PageContent>
    </Page>
  )
}
