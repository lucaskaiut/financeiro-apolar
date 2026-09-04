import { useCallback, useEffect, useState } from 'react'
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
import { useBankAccountOptions } from '@/modules/bank-accounts/hooks/useBankAccounts'
import { useCompanyOptions } from '@/modules/companies/hooks/useCompanies'
import { useCostCenterOptions } from '@/modules/cost-centers/hooks/useCostCenters'
import { useCreditCardOptions } from '@/modules/credit-cards/hooks/useCreditCards'
import { categoriesService } from '@/modules/categories/services/categories.service'
import { accountSchema, type AccountFormValues } from '../schemas/account.schema'
import type { AccountPayload } from '../services/accounts.service'
import { PendingDocuments } from '../components/PendingDocuments'

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
  const form = useForm<AccountFormValues>({
    resolver: zodResolver(accountSchema),
    defaultValues: {
      type: 'payable',
      description: '',
      counterparty: '',
      bank_account_id: '',
      credit_card_id: '',
      company_id: '',
      cost_center_id: '',
      category_id: '',
      subcategory_id: '',
      value: '',
      due_date: '',
      purchase_date: '',
      expected_date: '',
      paid_date: '',
      observation: '',
      installments: false,
      installment_quantity: '2',
      installment_interval: 'monthly',
      ...defaultValues,
      ...(isCardPurchase && purchaseDate ? { purchase_date: purchaseDate } : {}),
    },
  })

  const [selectedSubcategory, setSelectedSubcategory] = useState<SearchSelectOption | null>(null)
  const [documents, setDocuments] = useState<File[]>([])

  const type = form.watch('type')
  const installments = form.watch('installments')
  const creditCardId = form.watch('credit_card_id')
  const usingCreditCard = mode === 'create' ? Boolean(creditCardId) : isCardPurchase
  const categoryType = type === 'receivable' ? 'income' : 'expense'

  const bankAccounts = useBankAccountOptions()
  const creditCards = useCreditCardOptions()
  const companies = useCompanyOptions()
  const costCenters = useCostCenterOptions()

  useEffect(() => {
    if (mode !== 'create' || !creditCardId) return

    form.setValue('type', 'payable')
    form.setValue('bank_account_id', '')
  }, [creditCardId, form, mode])

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
    const hasCreditCard = Boolean(values.credit_card_id)

    const payload: AccountPayload = {
      type: hasCreditCard ? 'payable' : values.type,
      description: values.description,
      counterparty: values.counterparty || null,
      bank_account_id: hasCreditCard ? null : values.bank_account_id || null,
      credit_card_id: hasCreditCard ? values.credit_card_id : null,
      company_id: values.company_id || null,
      cost_center_id: values.cost_center_id || null,
      category_id: values.category_id,
      subcategory_id: values.subcategory_id || null,
      value: Number(values.value),
      due_date: hasCreditCard ? null : values.due_date,
      purchase_date: values.purchase_date || null,
      expected_date: values.expected_date || null,
      paid_date: mode === 'edit' ? values.paid_date || null : undefined,
      observation: values.observation || null,
      installments:
        mode === 'create' && values.installments
          ? hasCreditCard
            ? { quantity: Number(values.installment_quantity) }
            : { quantity: Number(values.installment_quantity), interval: values.installment_interval }
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
              disabled={usingCreditCard}
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
              {mode === 'create' && (
                <SelectField
                  name="credit_card_id"
                  label="Cartão de crédito"
                  options={creditCards.data ?? []}
                  placeholder="Nenhum (fluxo normal)"
                  hint="Se informado, o vencimento segue o ciclo da fatura do cartão."
                  className="sm:col-span-2"
                />
              )}
              {!usingCreditCard && (
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
              <TextField
                name="purchase_date"
                label="Data da compra"
                type="date"
                required={usingCreditCard}
                hint={
                  usingCreditCard
                    ? 'O vencimento é calculado pelo ciclo da fatura do cartão.'
                    : 'Opcional'
                }
              />
              {!usingCreditCard && (
                <TextField name="due_date" label="Data de vencimento" type="date" required />
              )}
              {mode === 'edit' && usingCreditCard && (
                <TextField name="due_date" label="Data de vencimento" type="date" disabled />
              )}
              {mode === 'edit' && hasSettlement && (
                <TextField
                  name="paid_date"
                  label="Data da baixa"
                  type="date"
                  required
                  hint="Alterações refletem no fluxo de caixa realizado."
                />
              )}
              {!usingCreditCard && (
                <TextField
                  name="expected_date"
                  label={type === 'receivable' ? 'Data prevista de recebimento' : 'Data prevista de pagamento'}
                  type="date"
                />
              )}
            </div>
          </Section>

          <Section title="Observação">
            <TextareaField name="observation" rows={3} />
          </Section>

          {mode === 'create' && (
            <Section
              title="Parcelamento"
              description={
                usingCreditCard
                  ? 'Cada parcela entra em uma fatura mensal. A data da compra permanece a mesma.'
                  : 'Divida o valor em parcelas iguais.'
              }
            >
              <SwitchField name="installments" label="Parcelar este lançamento" />
              {installments && (
                <div className="mt-4 grid gap-4 sm:grid-cols-2">
                  <TextField name="installment_quantity" label="Quantidade de parcelas" type="number" min="1" max="120" />
                  {!usingCreditCard && (
                    <SelectField
                      name="installment_interval"
                      label="Intervalo entre parcelas"
                      options={[
                        { value: 'daily', label: 'Diário' },
                        { value: 'weekly', label: 'Semanal' },
                        { value: 'monthly', label: 'Mensal' },
                      ]}
                    />
                  )}
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
