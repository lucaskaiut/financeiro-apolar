import { useNavigate, useSearchParams } from 'react-router'
import { Card, EmptyState, Page, PageContent, PageHeader, Skeleton } from '@/shared/design-system'
import { Banknote } from 'lucide-react'
import { toast } from '@/shared/stores/toast.store'
import { isApiError } from '@/shared/api/errors'
import { AccountForm } from '../forms/AccountForm'
import { useAccountQuery, useCreateAccount } from '../hooks/useAccounts'
import { accountsService } from '../services/accounts.service'
import { accountToCloneFormValues } from '../utils/clone'

export default function AccountCreatePage() {
  const navigate = useNavigate()
  const [searchParams] = useSearchParams()
  const cloneId = searchParams.get('clone')

  const create = useCreateAccount()
  const cloneQuery = useAccountQuery(cloneId ?? undefined)

  const isCloning = Boolean(cloneId)
  const cloneReady = !isCloning || cloneQuery.isSuccess

  return (
    <Page>
      <PageHeader
        title={isCloning ? 'Clonar lançamento' : 'Novo lançamento'}
        description={
          isCloning
            ? 'Os dados foram copiados do lançamento original. Ajuste o que precisar e salve.'
            : 'Cadastre uma conta a pagar ou a receber.'
        }
        breadcrumb={[
          { label: 'Dashboard', to: '/dashboard' },
          { label: 'Contas', to: '/accounts' },
          { label: isCloning ? 'Clonar' : 'Novo' },
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
            <EmptyState icon={Banknote} title="Lançamento não encontrado" description="Não foi possível carregar o lançamento para clonar." />
          </Card>
        )}

        {cloneReady && (!isCloning || cloneQuery.data) && (
          <AccountForm
            key={isCloning ? `clone-${cloneQuery.data!.id}` : 'new'}
            mode="create"
            defaultValues={cloneQuery.data ? accountToCloneFormValues(cloneQuery.data) : undefined}
            submitting={create.isPending}
            onSubmit={async (payload, documents) => {
              const result = await create.mutateAsync(payload)
              const accountId = result.data[0]?.id

              if (accountId && documents.length > 0) {
                try {
                  await accountsService.uploadDocuments(accountId, documents)
                  toast.success(
                    'Documentos anexados',
                    documents.length > 1 ? `${documents.length} documentos anexados com sucesso.` : 'Documento anexado com sucesso.',
                  )
                } catch (error) {
                  toast.error('Falha ao anexar', isApiError(error) ? error.message : 'Os documentos não foram anexados.')
                }
              }

              navigate('/accounts')
            }}
          />
        )}
      </PageContent>
    </Page>
  )
}
