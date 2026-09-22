import { useState } from 'react'
import { Button, ButtonLink, Form } from '@/shared/design-system'
import { isApiError } from '@/shared/api/errors'
import { applyApiErrorsToForm } from '@/shared/utils/forms'
import type { AccountFormValues } from '../schemas/account.schema'
import { AccountFormFields } from './AccountFormFields'
import { buildAccountPayload, useAccountForm } from './useAccountForm'
import type { AccountPayload } from '../services/accounts.service'

interface AccountFormProps {
  mode: 'create' | 'edit'
  defaultValues?: Partial<AccountFormValues>
  submitting: boolean
  hasSettlement?: boolean
  isCardPurchase?: boolean
  purchaseDate?: string | null
  onSubmit: (payload: AccountPayload, documents: File[]) => Promise<unknown>
}

export function AccountForm({
  mode,
  defaultValues,
  submitting,
  hasSettlement = false,
  isCardPurchase = false,
  purchaseDate = null,
  onSubmit,
}: AccountFormProps) {
  const form = useAccountForm({ mode, defaultValues, isCardPurchase, purchaseDate })
  const [documents, setDocuments] = useState<File[]>([])

  const handleSubmit = async (values: AccountFormValues) => {
    const payload = buildAccountPayload(values, { mode, isCardPurchase })

    try {
      await onSubmit(payload, mode === 'create' ? documents : [])
    } catch (error) {
      if (isApiError(error) && error.status === 422) {
        applyApiErrorsToForm(form, error)
      }
    }
  }

  return (
    <div className="mx-auto w-full max-w-[1200px]">
      <Form form={form} onSubmit={handleSubmit} className="space-y-6">
        <AccountFormFields
          mode={mode}
          hasSettlement={hasSettlement}
          isCardPurchase={isCardPurchase}
          documents={documents}
          onDocumentsChange={mode === 'create' ? setDocuments : undefined}
          submitting={submitting}
        />

        <div className="sticky bottom-0 z-10 border-t border-surface-2 bg-background/95 py-4 backdrop-blur">
          <div className="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
            <ButtonLink to="/accounts" variant="secondary">
              Cancelar
            </ButtonLink>
            <Button type="submit" loading={submitting}>
              {mode === 'create' ? 'Criar lançamento' : 'Salvar alterações'}
            </Button>
          </div>
        </div>
      </Form>
    </div>
  )
}
