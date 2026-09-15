import { useMemo, useState } from 'react'
import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import {
  Button,
  ButtonLink,
  Card,
  CardContent,
  Form,
  RadioGroupField,
  Section,
  SelectField,
  TextareaField,
  TextField,
  type SearchSelectOption,
} from '@/shared/design-system'
import {
  CategorySearchSelectField,
  SubcategorySearchSelectField,
} from '@/modules/categories/components/CategorySearchSelect'
import { isApiError } from '@/shared/api/errors'
import { applyApiErrorsToForm } from '@/shared/utils/forms'
import { useBankAccountOptions } from '@/modules/bank-accounts/hooks/useBankAccounts'
import { useCompanyOptions } from '@/modules/companies/hooks/useCompanies'
import { useCostCenterOptions } from '@/modules/cost-centers/hooks/useCostCenters'
import { createInstallmentSchema, type InstallmentFormValues } from '../schemas/installment.schema'
import type { InstallmentPayload } from '../services/installments.service'

interface InstallmentFormProps {
  defaultValues: InstallmentFormValues
  submitting: boolean
  isCardPurchase?: boolean
  onSubmit: (payload: InstallmentPayload) => Promise<unknown>
}

export function InstallmentForm({ defaultValues, submitting, isCardPurchase = false, onSubmit }: InstallmentFormProps) {
  const schema = useMemo(() => createInstallmentSchema(isCardPurchase), [isCardPurchase])

  const form = useForm<InstallmentFormValues>({
    resolver: zodResolver(schema),
    defaultValues,
  })

  const [selectedSubcategory, setSelectedSubcategory] = useState<SearchSelectOption | null>(null)

  const type = form.watch('type')
  const scope = form.watch('scope')
  const categoryType = type === 'receivable' ? 'income' : 'expense'
  const categoryId = form.watch('category_id')

  const bankAccounts = useBankAccountOptions()
  const companies = useCompanyOptions()
  const costCenters = useCostCenterOptions()

  const handleSubmit = async (values: InstallmentFormValues) => {
    const payload: InstallmentPayload = {
      description: values.description,
      counterparty: values.counterparty || null,
      company_id: values.company_id || null,
      cost_center_id: values.cost_center_id || null,
      category_id: values.category_id || null,
      subcategory_id: values.subcategory_id || null,
      expected_date: values.expected_date || null,
      observation: values.observation || null,
      scope: values.scope,
    }

    if (!isCardPurchase) {
      payload.type = values.type
      payload.bank_account_id = values.bank_account_id || null
    }

    if (values.scope === 'all') {
      payload.value = Number(values.value)
    }

    try {
      await onSubmit(payload)
    } catch (error) {
      if (isApiError(error) && error.status === 422) {
        applyApiErrorsToForm(form, error)
      }
    }
  }

  return (
    <Card>
      <CardContent>
        <Form form={form} onSubmit={handleSubmit} className="space-y-8">
          {!isCardPurchase && (
            <Section title="Tipo de lançamento">
              <RadioGroupField
                name="type"
                options={[
                  { value: 'payable', label: 'Conta a pagar' },
                  { value: 'receivable', label: 'Conta a receber' },
                ]}
              />
            </Section>
          )}

          <Section title="Informações do lançamento">
            <div className="grid gap-4 sm:grid-cols-2">
              <TextField name="description" label="Descrição" required className="sm:col-span-2" />
              <TextField
                name="counterparty"
                label={type === 'receivable' ? 'Cliente' : 'Fornecedor'}
                className="sm:col-span-2"
              />
              {!isCardPurchase && (
                <SelectField
                  name="bank_account_id"
                  label="Conta bancária"
                  options={bankAccounts.data ?? []}
                  placeholder="Selecione"
                  required
                />
              )}
              <SelectField
                name="company_id"
                label="Empresa"
                options={companies.data ?? []}
                placeholder="Opcional"
              />
              <SelectField
                name="cost_center_id"
                label="Centro de custo"
                options={costCenters.data ?? []}
                placeholder="Opcional"
              />
              <CategorySearchSelectField
                name="category_id"
                label="Categoria"
                categoryType={categoryType}
                required
                onSelectOption={(option) => {
                  const subcategoryId = form.getValues('subcategory_id')
                  if (subcategoryId && selectedSubcategory?.parent_id !== option.value) {
                    form.setValue('subcategory_id', '')
                    setSelectedSubcategory(null)
                  }
                }}
              />
              <SubcategorySearchSelectField
                name="subcategory_id"
                label="Subcategoria"
                categoryType={categoryType}
                categoryId={categoryId}
                onCategoryChange={(parentId) => form.setValue('category_id', parentId)}
                onSelectOption={(option) => setSelectedSubcategory(option)}
              />
              {scope === 'all' && (
                <TextField
                  name="value"
                  label="Valor total"
                  type="number"
                  step="0.01"
                  min="0"
                  required
                  hint="O valor total é redistribuído igualmente entre todas as parcelas."
                />
              )}
              <TextField
                name="expected_date"
                label={type === 'receivable' ? 'Data prevista de recebimento' : 'Data prevista de pagamento'}
                type="date"
              />
            </div>
          </Section>

          <Section title="Observação">
            <TextareaField name="observation" rows={3} />
          </Section>

          <Section title="Alcance da alteração" description="Defina quais parcelas serão afetadas.">
            <SelectField
              name="scope"
              options={[
                { value: 'all', label: 'Alterar toda a série' },
                { value: 'future', label: 'Alterar parcelas futuras' },
              ]}
            />
          </Section>

          <div className="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
            <ButtonLink to="/installments" variant="secondary">
              Cancelar
            </ButtonLink>
            <Button type="submit" loading={submitting}>
              Salvar alterações
            </Button>
          </div>
        </Form>
      </CardContent>
    </Card>
  )
}
