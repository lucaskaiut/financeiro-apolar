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
  SwitchField,
  TextareaField,
  TextField,
  type SearchSelectOption,
} from '@/shared/design-system'
import { isApiError } from '@/shared/api/errors'
import { applyApiErrorsToForm } from '@/shared/utils/forms'
import { useCostCenterOptions } from '@/modules/cost-centers/hooks/useCostCenters'
import { categoriesService } from '@/modules/categories/services/categories.service'
import { accountSchema, type AccountFormValues } from '../schemas/account.schema'
import type { AccountPayload } from '../services/accounts.service'
import { PendingDocuments } from '../components/PendingDocuments'

interface AccountFormProps {
  mode: 'create' | 'edit'
  defaultValues?: Partial<AccountFormValues>
  submitting: boolean
  hasSettlement?: boolean
  onSubmit: (payload: AccountPayload, documents: File[]) => Promise<unknown>
}

export function AccountForm({ mode, defaultValues, submitting, hasSettlement = false, onSubmit }: AccountFormProps) {
  const form = useForm<AccountFormValues>({
    resolver: zodResolver(accountSchema),
    defaultValues: {
      type: 'payable',
      description: '',
      counterparty: '',
      cost_center_id: '',
      category_id: '',
      subcategory_id: '',
      value: '',
      due_date: '',
      expected_date: '',
      paid_date: '',
      observation: '',
      installments: false,
      installment_quantity: '2',
      installment_interval: 'monthly',
      ...defaultValues,
    },
  })

  const [selectedSubcategory, setSelectedSubcategory] = useState<SearchSelectOption | null>(null)
  const [documents, setDocuments] = useState<File[]>([])

  const type = form.watch('type')
  const installments = form.watch('installments')
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

  const handleSubmit = async (values: AccountFormValues) => {
    const payload: AccountPayload = {
      type: values.type,
      description: values.description,
      counterparty: values.counterparty || null,
      cost_center_id: values.cost_center_id,
      category_id: values.category_id,
      subcategory_id: values.subcategory_id || null,
      value: Number(values.value),
      due_date: values.due_date,
      expected_date: values.expected_date || null,
      paid_date: mode === 'edit' ? values.paid_date || null : undefined,
      observation: values.observation || null,
      installments:
        mode === 'create' && values.installments
          ? { quantity: Number(values.installment_quantity), interval: values.installment_interval }
          : null,
    }

    try {
      await onSubmit(payload, mode === 'create' ? documents : [])
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
          <Section
            title="Tipo de lançamento"
            description={hasSettlement ? 'Alterações refletem no fluxo de caixa realizado.' : undefined}
          >
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
              <TextField
                name="counterparty"
                label={type === 'receivable' ? 'Cliente' : 'Fornecedor'}
                className="sm:col-span-2"
              />
              <SelectField
                name="cost_center_id"
                label="Centro de custo"
                options={costCenters.data ?? []}
                placeholder="Selecione"
                required
              />
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
              <TextField
                name="value"
                label="Valor"
                type="number"
                step="0.01"
                min="0"
                required
                hint={hasSettlement ? 'Alterações refletem no fluxo de caixa realizado.' : undefined}
              />
              <TextField name="due_date" label="Data de vencimento" type="date" required />
              {mode === 'edit' && hasSettlement && (
                <TextField
                  name="paid_date"
                  label="Data da baixa"
                  type="date"
                  required
                  hint="Alterações refletem no fluxo de caixa realizado."
                />
              )}
              <TextField name="expected_date" label={type === 'receivable' ? 'Data prevista de recebimento' : 'Data prevista de pagamento'} type="date" />
            </div>
          </Section>

          <Section title="Observação">
            <TextareaField name="observation" rows={3} />
          </Section>

          {mode === 'create' && (
            <Section title="Parcelamento" description="Divida o valor em parcelas iguais.">
              <SwitchField name="installments" label="Parcelar este lançamento" />
              {installments && (
                <div className="grid gap-4 sm:grid-cols-2">
                  <TextField name="installment_quantity" label="Quantidade de parcelas" type="number" min="1" max="120" />
                  <SelectField
                    name="installment_interval"
                    label="Intervalo entre parcelas"
                    options={[
                      { value: 'daily', label: 'Diário' },
                      { value: 'weekly', label: 'Semanal' },
                      { value: 'monthly', label: 'Mensal' },
                    ]}
                  />
                </div>
              )}
            </Section>
          )}

          {mode === 'create' && (
            <Section
              title="Documentos"
              description="Anexe faturas, boletos e comprovantes. Os arquivos serão salvos após a criação do lançamento."
            >
              <PendingDocuments files={documents} onChange={setDocuments} disabled={submitting} />
            </Section>
          )}

          <div className="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
            <ButtonLink to="/accounts" variant="secondary">
              Cancelar
            </ButtonLink>
            <Button type="submit" loading={submitting}>
              {mode === 'create' ? 'Criar lançamento' : 'Salvar alterações'}
            </Button>
          </div>
        </Form>
      </CardContent>
    </Card>
  )
}
