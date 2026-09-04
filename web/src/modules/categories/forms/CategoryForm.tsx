import { useCallback } from 'react'
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
import { categoriesService } from '../services/categories.service'
import { categorySchema, type CategoryFormValues } from '../schemas/category.schema'
import type { CategoryPayload } from '../services/categories.service'

const STATUS_OPTIONS = [
  { value: 'active', label: 'Ativo' },
  { value: 'inactive', label: 'Inativo' },
]

const COLOR_OPTIONS = [
  { value: '#6366f1', label: 'Índigo' },
  { value: '#0ea5e9', label: 'Azul' },
  { value: '#10b981', label: 'Verde' },
  { value: '#f59e0b', label: 'Âmbar' },
  { value: '#ef4444', label: 'Vermelho' },
  { value: '#8b5cf6', label: 'Roxo' },
  { value: '#64748b', label: 'Cinza' },
]

interface CategoryFormProps {
  mode: 'create' | 'edit'
  defaultValues?: Partial<CategoryFormValues>
  submitting: boolean
  onSubmit: (payload: CategoryPayload) => Promise<unknown>
}

export function CategoryForm({ mode, defaultValues, submitting, onSubmit }: CategoryFormProps) {
  const form = useForm<CategoryFormValues>({
    resolver: zodResolver(categorySchema),
    defaultValues: {
      name: '',
      type: 'expense',
      color: '#6366f1',
      status: 'active',
      parent_id: '',
      ...defaultValues,
    },
  })

  const parentId = form.watch('parent_id')

  const loadParentCategories = useCallback(async (search: string): Promise<SearchSelectOption[]> => {
    const result = await categoriesService.list({
      search: search || undefined,
      parent: 'root',
      per_page: 20,
    })

    return result.data.map((category) => ({ value: category.id, label: category.name, type: category.type }))
  }, [])

  const resolveParentLabel = useCallback(async (id: string): Promise<SearchSelectOption | null> => {
    try {
      const category = await categoriesService.get(id)
      return { value: category.id, label: category.name }
    } catch {
      return null
    }
  }, [])

  const handleSubmit = async (values: CategoryFormValues) => {
    try {
      await onSubmit({
        name: values.name,
        type: values.type,
        color: values.color || null,
        status: values.status,
        parent_id: values.parent_id || null,
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
          <Section title="Dados da categoria">
            <div className="grid gap-4 sm:grid-cols-2">
              <TextField name="name" label="Nome" placeholder="Ex.: Fornecedores" required className="sm:col-span-2" />
              <SearchSelectField
                name="parent_id"
                label="Categoria pai"
                loadOptions={loadParentCategories}
                resolveLabel={resolveParentLabel}
                placeholder="Buscar categoria principal..."
                hint="Selecione uma categoria pai para criar uma subcategoria."
                onSelectOption={(option) => {
                  if (option.type) form.setValue('type', option.type as CategoryFormValues['type'])
                }}
              />
              <SelectField name="color" label="Cor" options={COLOR_OPTIONS} />
              <SelectField name="status" label="Status" options={STATUS_OPTIONS} required />
            </div>
          </Section>

          <Section title="Tipo" description={parentId ? 'O tipo é herdado da categoria pai.' : undefined}>
            <RadioGroupField
              name="type"
              options={[
                { value: 'income', label: 'Receita' },
                { value: 'expense', label: 'Despesa' },
              ]}
            />
          </Section>

          <div className="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
            <ButtonLink to="/categories" variant="secondary">
              Cancelar
            </ButtonLink>
            <Button type="submit" loading={submitting}>
              {mode === 'create' ? 'Criar categoria' : 'Salvar alterações'}
            </Button>
          </div>
        </Form>
      </CardContent>
    </Card>
  )
}
