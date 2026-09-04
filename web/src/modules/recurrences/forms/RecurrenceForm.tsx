import { useCallback, useState } from 'react'
import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import {
  Button,
  ButtonLink,
  Card,
  CardContent,
  Form,
  RadioGroupField,
  SearchSelectField,
  Section,
  SelectField,
  TextField,
  type SearchSelectOption,
} from '@/shared/design-system'
import { isApiError } from '@/shared/api/errors'
import { applyApiErrorsToForm } from '@/shared/utils/forms'
import { useCostCenterOptions } from '@/modules/cost-centers/hooks/useCostCenters'
import { categoriesService } from '@/modules/categories/services/categories.service'
import { recurrenceSchema, type RecurrenceFormValues } from '../schemas/recurrence.schema'
import type { RecurrencePayload } from '../services/recurrences.service'

const FREQUENCY_OPTIONS = [
  { value: 'daily', label: 'Diária' },
  { value: 'weekly', label: 'Semanal' },
  { value: 'biweekly', label: 'Quinzenal' },
  { value: 'monthly', label: 'Mensal' },
  { value: 'bimonthly', label: 'Bimestral' },
  { value: 'quarterly', label: 'Trimestral' },
  { value: 'semiannual', label: 'Semestral' },
  { value: 'annual', label: 'Anual' },
]

interface RecurrenceFormProps {
  mode: 'create' | 'edit'
  defaultValues?: Partial<RecurrenceFormValues>
  submitting: boolean
  onSubmit: (payload: RecurrencePayload) => Promise<unknown>
}

export function RecurrenceForm({ mode, defaultValues, submitting, onSubmit }: RecurrenceFormProps) {
  const form = useForm<RecurrenceFormValues>({
    resolver: zodResolver(recurrenceSchema),
    defaultValues: {
      type: 'payable',
      description: '',
      counterparty: '',
      cost_center_id: '',
      category_id: '',
      subcategory_id: '',
      value: '',
      frequency: 'monthly',
      start_date: '',
      end_date: '',
      max_occurrences: '',
      day_of_month: '',
      scope: 'all',
      ...defaultValues,
    },
  })

  const [selectedSubcategory, setSelectedSubcategory] = useState<SearchSelectOption | null>(null)

  const type = form.watch('type')
  const categoryType = type === 'receivable' ? 'income' : 'expense'

  const costCenters = useCostCenterOptions()

  const loadCategories = useCallback(
    async (search: string): Promise<SearchSelectOption[]> => {
      const result = await categoriesService.list({
        search: search || undefined,
        type: categoryType,
        parent: 'root',
        per_page: 20,
      })

      return result.data.map((category) => ({ value: category.id, label: category.name }))
    },
    [categoryType],
  )

  const loadSubcategories = useCallback(
    async (search: string): Promise<SearchSelectOption[]> => {
      const result = await categoriesService.list({
        search: search || undefined,
        type: categoryType,
        parent: form.getValues('category_id') || 'sub',
        per_page: 20,
      })

      return result.data.map((category) => ({
        value: category.id,
        label: category.name,
        parent_id: category.parent_id,
      }))
    },
    [categoryType, form],
  )

  const resolveLabel = useCallback(async (id: string): Promise<SearchSelectOption | null> => {
    try {
      const category = await categoriesService.get(id)
      return { value: category.id, label: category.name }
    } catch {
      return null
    }
  }, [])

  const handleSubmit = async (values: RecurrenceFormValues) => {
    try {
      await onSubmit({
        type: values.type,
        description: values.description,
        counterparty: values.counterparty || null,
        cost_center_id: values.cost_center_id,
        category_id: values.category_id,
        subcategory_id: values.subcategory_id || null,
        value: Number(values.value),
        frequency: values.frequency,
        start_date: values.start_date,
        end_date: values.end_date || null,
        max_occurrences: values.max_occurrences === '' ? null : Number(values.max_occurrences),
        day_of_month: values.day_of_month === '' ? null : Number(values.day_of_month),
        scope: mode === 'edit' ? values.scope : undefined,
      })
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
          <Section title="Tipo de lançamento">
            <RadioGroupField
              name="type"
              options={[
                { value: 'payable', label: 'Conta a pagar' },
                { value: 'receivable', label: 'Conta a receber' },
              ]}
            />
          </Section>

          <Section title="Informações do lançamento">
            <div className="grid gap-4 sm:grid-cols-2">
              <TextField name="description" label="Descrição" required className="sm:col-span-2" />
              <TextField name="counterparty" label={type === 'receivable' ? 'Cliente' : 'Fornecedor'} className="sm:col-span-2" />
              <SelectField name="cost_center_id" label="Centro de custo" options={costCenters.data ?? []} placeholder="Selecione" required />
              <SearchSelectField
                name="category_id"
                label="Categoria"
                loadOptions={loadCategories}
                resolveLabel={resolveLabel}
                placeholder="Buscar categoria..."
                required
                onSelectOption={(option) => {
                  const subcategoryId = form.getValues('subcategory_id')
                  if (subcategoryId && selectedSubcategory?.parent_id !== option.value) {
                    form.setValue('subcategory_id', '')
                    setSelectedSubcategory(null)
                  }
                }}
              />
              <SearchSelectField
                name="subcategory_id"
                label="Subcategoria"
                loadOptions={loadSubcategories}
                resolveLabel={resolveLabel}
                placeholder="Buscar subcategoria..."
                onSelectOption={(option) => {
                  setSelectedSubcategory(option)
                  const categoryId = form.getValues('category_id')
                  if (option.parent_id && option.parent_id !== categoryId) {
                    form.setValue('category_id', option.parent_id)
                  }
                }}
              />
              <TextField name="value" label="Valor" type="number" step="0.01" min="0" required />
              <SelectField name="frequency" label="Frequência" options={FREQUENCY_OPTIONS} required />
              <TextField name="start_date" label="Data inicial" type="date" required />
              <TextField name="day_of_month" label="Dia do mês (opcional)" type="number" min="1" max="31" hint="Ex.: 10 para cobrar todo dia 10" />
            </div>
          </Section>

          <Section title="Limite da recorrência">
            <div className="grid gap-4 sm:grid-cols-2">
              <TextField name="end_date" label="Data final" type="date" />
              <TextField name="max_occurrences" label="Quantidade máxima de ocorrências" type="number" min="0" max="366" />
            </div>
          </Section>

          {mode === 'edit' && (
            <Section title="Alcance da alteração" description="Defina quais lançamentos serão afetados.">
              <SelectField
                name="scope"
                options={[
                  { value: 'all', label: 'Alterar toda a série' },
                  { value: 'future', label: 'Alterar ocorrências futuras' },
                  { value: 'current', label: 'Alterar apenas a recorrência' },
                ]}
              />
            </Section>
          )}

          <div className="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
            <ButtonLink to="/recurrences" variant="secondary">
              Cancelar
            </ButtonLink>
            <Button type="submit" loading={submitting}>
              {mode === 'create' ? 'Criar recorrência' : 'Salvar alterações'}
            </Button>
          </div>
        </Form>
      </CardContent>
    </Card>
  )
}
