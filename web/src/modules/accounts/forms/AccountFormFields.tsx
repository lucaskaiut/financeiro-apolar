import { useState, type ReactNode } from 'react'
import { useFormContext } from 'react-hook-form'
import {
  Card,
  CardContent,
  RadioGroupField,
  SelectField,
  SwitchField,
  TextareaField,
  TextField,
  type SearchSelectOption,
} from '@/shared/design-system'
import {
  CategorySearchSelectField,
  SubcategorySearchSelectField,
} from '@/modules/categories/components/CategorySearchSelect'
import { cn } from '@/shared/utils/cn'
import { useBankAccountOptions } from '@/modules/bank-accounts/hooks/useBankAccounts'
import { useCostCenterOptions } from '@/modules/cost-centers/hooks/useCostCenters'
import { useCreditCardOptions } from '@/modules/credit-cards/hooks/useCreditCards'
import { PendingDocuments } from '../components/PendingDocuments'
import type { AccountFormValues } from '../schemas/account.schema'
import { AllocationFields } from './AllocationFields'
import { InstallmentItemsFields } from './InstallmentItemsFields'

function FormSection({
  title,
  description,
  className,
  children,
}: {
  title: string
  description?: string
  className?: string
  children: ReactNode
}) {
  return (
    <Card className={cn('border border-surface-2', className)}>
      <CardContent className="space-y-5 p-6">
        <div>
          <h2 className="text-base font-semibold text-foreground">{title}</h2>
          {description && <p className="mt-1 text-sm text-muted">{description}</p>}
        </div>
        {children}
      </CardContent>
    </Card>
  )
}

export interface AccountFormFieldsProps {
  mode: 'create' | 'edit'
  hasSettlement?: boolean
  isCardPurchase?: boolean
  allowCreditCard?: boolean
  documents?: File[]
  onDocumentsChange?: (files: File[]) => void
  submitting?: boolean
}

export function AccountFormFields({
  mode,
  hasSettlement = false,
  isCardPurchase = false,
  allowCreditCard = true,
  documents = [],
  onDocumentsChange,
  submitting = false,
}: AccountFormFieldsProps) {
  const form = useFormContext<AccountFormValues>()
  const [selectedSubcategory, setSelectedSubcategory] = useState<SearchSelectOption | null>(null)

  const type = form.watch('type')
  const installments = form.watch('installments')
  const customizeInstallments = form.watch('customize_installments')
  const split = form.watch('split')
  const creditCardId = form.watch('credit_card_id')
  const categoryId = form.watch('category_id')
  const usingCreditCard = allowCreditCard && (mode === 'create' ? Boolean(creditCardId) : isCardPurchase)
  const categoryType = type === 'receivable' ? 'income' : 'expense'

  const bankAccounts = useBankAccountOptions()
  const creditCards = useCreditCardOptions(allowCreditCard)
  const costCenters = useCostCenterOptions()

  return (
    <>
      <FormSection
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
      </FormSection>

      <FormSection title="Informações do lançamento">
        <div className="grid gap-5 sm:grid-cols-2">
          <TextField name="description" label="Descrição" required className="sm:col-span-2" />
          <TextField
            name="counterparty"
            label={type === 'receivable' ? 'Cliente' : 'Fornecedor'}
            className="sm:col-span-2"
          />
          {!usingCreditCard && (
            <SelectField
              name="bank_account_id"
              label="Conta bancária"
              options={bankAccounts.data ?? []}
              placeholder="Selecione"
              required
            />
          )}
          {allowCreditCard && mode === 'create' && (
            <SelectField
              name="credit_card_id"
              label="Cartão de crédito"
              options={creditCards.data ?? []}
              placeholder="Nenhum (fluxo normal)"
              hint="Se informado, o vencimento segue o ciclo da fatura do cartão."
            />
          )}
        </div>
      </FormSection>

      <FormSection title="Valores" description="Dados financeiros do lançamento.">
        <div className="grid gap-5 sm:grid-cols-2">
          <TextField
            name="value"
            label="Valor"
            type="number"
            step="0.01"
            min="0"
            required
            hint={hasSettlement ? 'Alterações refletem no fluxo de caixa realizado.' : undefined}
            className="sm:col-span-2"
          />
          {!split && (
            <>
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
              <SelectField
                name="cost_center_id"
                label="Centro de custo"
                options={costCenters.data ?? []}
                placeholder="Selecione"
                required
              />
            </>
          )}
        </div>
      </FormSection>

      <FormSection title="Datas">
        <div className="grid gap-5 sm:grid-cols-3">
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
          {!usingCreditCard && (
            <TextField
              name="expected_date"
              label={type === 'receivable' ? 'Data prevista de recebimento' : 'Data prevista de pagamento'}
              type="date"
            />
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
        </div>
      </FormSection>

      <FormSection title="Observações">
        <TextareaField name="observation" rows={4} />
      </FormSection>

      <FormSection
        title="Rateio"
        description="Distribua o valor entre categorias e centros de custo. A conciliação continua em um único lançamento."
        className="border-dashed border-surface-3 bg-surface-2/40"
      >
        <SwitchField
          name="split"
          label="Ratear este lançamento"
          hint="Os relatórios separam as fatias; o extrato concilia o valor total."
        />
        {split && (
          <div className="mt-5">
            <AllocationFields categoryType={categoryType} />
          </div>
        )}
      </FormSection>

      {mode === 'create' && (
        <FormSection
          title="Parcelamento"
          description={
            usingCreditCard
              ? 'Cada parcela entra em uma fatura mensal. A data da compra permanece a mesma.'
              : 'Divida o valor em parcelas iguais ou personalize cada parcela.'
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
          {installments && !usingCreditCard && (
            <div className="mt-4">
              <SwitchField
                name="customize_installments"
                label="Personalizar parcelas"
                hint="Defina o valor e o vencimento de cada parcela individualmente. A soma deve ser igual ao valor do lançamento."
              />
              {customizeInstallments && <InstallmentItemsFields />}
            </div>
          )}
        </FormSection>
      )}

      {mode === 'create' && onDocumentsChange && (
        <FormSection
          title="Documentos"
          description="Anexe faturas, boletos e comprovantes. Os arquivos serão salvos após a criação do lançamento."
        >
          <PendingDocuments files={documents} onChange={onDocumentsChange} disabled={submitting} />
        </FormSection>
      )}
    </>
  )
}
