import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { queryKeys } from '@/shared/constants/query-keys'
import type { ListParams } from '@/shared/types/api'
import { toast } from '@/shared/stores/toast.store'
import {
  creditCardsService,
  type CreditCardPayload,
  type PurchasePayload,
} from '../services/credit-cards.service'

export function useCreditCardsQuery(params: ListParams) {
  return useQuery({
    queryKey: queryKeys.creditCards.list(params),
    queryFn: () => creditCardsService.list(params),
    placeholderData: keepPreviousData,
  })
}

export function useCreditCardOptions() {
  return useQuery({
    queryKey: queryKeys.creditCards.list({ per_page: 100 }),
    queryFn: () => creditCardsService.list({ per_page: 100 }),
    select: (data) => data.data.map((c) => ({ value: c.id, label: c.name })),
  })
}

export function useCreditCardQuery(id: string | undefined) {
  return useQuery({
    queryKey: queryKeys.creditCards.detail(id ?? ''),
    queryFn: () => creditCardsService.get(id!),
    enabled: !!id,
  })
}

export function useCreditCardInvoices(id: string | undefined) {
  return useQuery({
    queryKey: queryKeys.creditCards.invoices(id ?? ''),
    queryFn: () => creditCardsService.listInvoices(id!),
    enabled: !!id,
  })
}

export function useCreateCreditCard() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: (payload: CreditCardPayload) => creditCardsService.create(payload),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: queryKeys.creditCards.all })
      toast.success('Cartão criado', 'O cartão de crédito foi criado com sucesso.')
    },
  })
}

export function useUpdateCreditCard(id: string) {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: (payload: CreditCardPayload) => creditCardsService.update(id, payload),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: queryKeys.creditCards.all })
      toast.success('Cartão atualizado', 'As alterações foram salvas.')
    },
  })
}

export function useDeleteCreditCard() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: (id: string) => creditCardsService.remove(id),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: queryKeys.creditCards.all })
      toast.success('Cartão removido', 'O cartão de crédito foi excluído.')
    },
  })
}

export function useCreatePurchase(cardId: string) {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: (payload: PurchasePayload) => creditCardsService.createPurchase(cardId, payload),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: queryKeys.creditCards.invoices(cardId) })
      queryClient.invalidateQueries({ queryKey: queryKeys.accounts.all })
      toast.success('Compra registrada', 'A compra no cartão foi registrada.')
    },
  })
}

export function useCloseInvoice(cardId: string) {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: (referenceMonth: string) => creditCardsService.closeInvoice(cardId, referenceMonth),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: queryKeys.creditCards.invoices(cardId) })
      queryClient.invalidateQueries({ queryKey: queryKeys.accounts.all })
      toast.success('Fatura fechada', 'A fatura foi fechada e a conta a pagar foi gerada.')
    },
  })
}
