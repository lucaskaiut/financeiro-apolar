import { useCallback } from 'react'
import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import {
  Button,
  Card,
  CardContent,
  Form,
  SearchSelectField,
  Section,
  SelectField,
  TextField,
  TextareaField,
  type SearchSelectOption,
} from '@/shared/design-system'
import { isApiError } from '@/shared/api/errors'
import { applyApiErrorsToForm } from '@/shared/utils/forms'
import { useCompanyOptions } from '@/modules/companies/hooks/useCompanies'
import { useCostCenterOptions } from '@/modules/cost-centers/hooks/useCostCenters'
import { categoriesService } from '@/modules/categories/services/categories.service'
import { purchaseSchema, type PurchaseFormValues } from '../schemas/credit-card.schema'
import type { PurchasePayload } from '../services/credit-cards.service'

interface PurchaseFormProps {
  submitting: boolean
  onSubmit: (payload: PurchasePayload) => Promise<unknown>
}

export function PurchaseForm({ submitting, onSubmit }: PurchaseFormProps) {
  const companies = useCompanyOptions()
  const costCenters = useCostCenterOptions()

  const form = useForm<PurchaseFormValues>({
    resolver: zodResolver(purchaseSchema),
    defaultValues: {
      description: '',
      counterparty: '',
      company_id: '',
      cost_center_id: '',
      category_id: '',
      subcategory_id: '',
      value: '',
      due_date: '',
      observation: '',
    },
  })

  const loadCategories = useCallback(async (search: string): Promise<SearchSelectOption[]> => {
    const result = await categoriesService.list({
      search: search || undefined,
      type: 'expense',
      parent: 'root',
      per_page: 20,
    })

    return result.data.map((category) => ({ value: category.id, label: category.name }))
  }, [])

  const resolveLabel = useCallback(async (id: string): Promise<SearchSelectOption | null> => {
    try {
      const category = await categoriesService.get(id)
      return { value: category.id, label: category.name }
    } catch {
      return null
    }
  }, [])

  const handleSubmit = async (values: PurchaseFormValues) => {
    try {
      await onSubmit({
        description: values.description,
        counterparty: values.counterparty || null,
        company_id: values.company_id || null,
        cost_center_id: values.cost_center_id || null,
        category_id: values.category_id,
        subcategory_id: values.subcategory_id || null,
        value: Number(values.value),
        due_date: values.due_date,
        observation: values.observation || null,
      })
      form.reset()
    } catch (error) {
      if (isApiError(error) && error.status === 422) {
        applyApiErrorsToForm(form, error)
      }
    }
  }

  return (
    <Card>
      <CardContent>
        <Form form={form} onSubmit={handleSubmit} className="space-y-6">
          <Section title="Nova compra" description="Registre uma compra no cartão de crédito.">
            <div className="grid gap-4 sm:grid-cols-2">
              <TextField name="description" label="Descrição" required className="sm:col-span-2" />
              <TextField name="counterparty" label="Fornecedor" className="sm:col-span-2" />
              <SelectField name="company_id" label="Empresa" options={companies.data ?? []} placeholder="Opcional" />
              <SelectField name="cost_center_id" label="Centro de custo" options={costCenters.data ?? []} placeholder="Opcional" />
              <SearchSelectField
                name="category_id"
                label="Categoria"
                loadOptions={loadCategories}
                resolveLabel={resolveLabel}
                placeholder="Buscar categoria..."
                required
              />
              <TextField name="value" label="Valor" type="number" step="0.01" min="0" required />
              <TextField name="due_date" label="Data da compra" type="date" required />
            </div>
            <TextareaField name="observation" label="Observação" rows={2} className="mt-4" />
          </Section>

          <div className="flex justify-end">
            <Button type="submit" loading={submitting}>
              Registrar compra
            </Button>
          </div>
        </Form>
      </CardContent>
    </Card>
  )
}
