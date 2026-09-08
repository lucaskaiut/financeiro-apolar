<?php

namespace App\Modules\Account\Services;

use App\Modules\Account\Enums\AccountStatus;
use App\Modules\Account\Enums\AllocationMode;
use App\Modules\Account\Exceptions\AccountUpdateException;
use App\Modules\Account\Models\FinancialAccount;
use App\Modules\Account\Models\Settlement;
use App\Modules\CreditCard\Models\CreditCard;
use App\Modules\CreditCard\Models\CreditCardInvoice;
use App\Modules\CreditCard\Services\CreditCardService;
use App\Modules\Reconciliation\Models\BankTransaction;
use App\Modules\Reconciliation\Models\Reconciliation;
use App\Modules\Shared\Support\DateOnly;
use App\Modules\User\Models\User;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class AccountService
{
    public function __construct(
        private readonly CreditCardService $creditCards,
        private readonly AllocationService $allocations,
    ) {}

    /**
     * @param  array{per_page?: int, search?: ?string, type?: ?string, status?: ?string, overdue?: bool|string|null, bank_account_id?: ?string, credit_card_id?: ?string, cost_center_id?: ?string, company_id?: ?string, category_id?: ?string, due_from?: ?string, due_to?: ?string, paid_from?: ?string, paid_to?: ?string, installment_group_id?: ?string}  $filters
     */
    public function paginate(int $perPage = 15, array $filters = []): LengthAwarePaginator
    {
        $query = FinancialAccount::query()
            ->with([
                'bankAccount:id,uuid,name',
                'company:id,uuid,name',
                'costCenter:id,uuid,name',
                'creditCard:id,uuid,name',
                'category:id,uuid,name,color,type',
                'subcategory:id,uuid,name',
                'allocations.costCenter:id,uuid,name',
                'allocations.category:id,uuid,name,color,type',
                'allocations.subcategory:id,uuid,name',
            ])
            ->withSum('settlements', 'value');

        $overdue = filter_var($filters['overdue'] ?? false, FILTER_VALIDATE_BOOLEAN);

        $query->when(filled($filters['type'] ?? null), fn ($q) => $q->where('type', $filters['type']));

        if ($overdue) {
            $query->whereIn('status', [AccountStatus::Open->value, AccountStatus::Partial->value])
                ->whereDate('due_date', '<', now()->toDateString());
        } else {
            $query->when(filled($filters['status'] ?? null), fn ($q) => $q->where('status', $filters['status']));
        }

        $query->when(filled($filters['bank_account_id'] ?? null), fn ($q) => $q->where('bank_account_id', $filters['bank_account_id']));
        $query->when(filled($filters['credit_card_id'] ?? null), fn ($q) => $q->where('credit_card_id', $filters['credit_card_id']));
        $query->when(filled($filters['cost_center_id'] ?? null), function ($q) use ($filters): void {
            $costCenterId = $filters['cost_center_id'];
            $q->where(function ($inner) use ($costCenterId): void {
                $inner->where('cost_center_id', $costCenterId)
                    ->orWhereHas('allocations', fn ($a) => $a->where('cost_center_id', $costCenterId));
            });
        });
        $query->when(filled($filters['company_id'] ?? null), function ($q) use ($filters): void {
            $companyId = $filters['company_id'];
            $q->where(function ($inner) use ($companyId): void {
                $inner->where('company_id', $companyId)
                    ->orWhereHas('allocations', fn ($a) => $a->where('company_id', $companyId));
            });
        });
        $query->when(filled($filters['category_id'] ?? null), function ($q) use ($filters): void {
            $q->forCategory($filters['category_id']);
        });
        $query->when(filled($filters['installment_group_id'] ?? null), fn ($q) => $q->where('installment_group_id', $filters['installment_group_id']));
        $query->when(filled($filters['due_from'] ?? null), fn ($q) => $q->whereDate('due_date', '>=', $filters['due_from']));
        $query->when(filled($filters['due_to'] ?? null), fn ($q) => $q->whereDate('due_date', '<=', $filters['due_to']));
        $query->when(filled($filters['paid_from'] ?? null), fn ($q) => $q->whereDate('paid_date', '>=', $filters['paid_from']));
        $query->when(filled($filters['paid_to'] ?? null), fn ($q) => $q->whereDate('paid_date', '<=', $filters['paid_to']));

        $query->when(filled($filters['search'] ?? null), function ($q) use ($filters): void {
            $search = $filters['search'];

            $q->where(function ($q) use ($search): void {
                $q->where('description', 'like', "%{$search}%")
                    ->orWhere('counterparty', 'like', "%{$search}%");
            });
        });

        // Compras já incluídas em fatura são liquidadas pelo pagamento da fatura —
        // ocultar da listagem geral para não poluir "a pagar" (visíveis no cartão/fatura).
        if (! filled($filters['credit_card_id'] ?? null)) {
            $query->where(function ($q): void {
                $q->where('is_card_purchase', false)
                    ->orWhereNull('credit_card_invoice_id');
            });
        }

        return $query
            ->orderBy('due_date')
            ->orderBy('id')
            ->paginate(min(max($perPage, 1), 100));
    }

    public function find(string $uuid): FinancialAccount
    {
        return FinancialAccount::query()
            ->with([
                'bankAccount:id,uuid,name',
                'company:id,uuid,name',
                'costCenter:id,uuid,name',
                'creditCard:id,uuid,name',
                'category:id,uuid,name,color,type',
                'subcategory:id,uuid,name',
                'settlements' => fn ($q) => $q->orderBy('settled_at'),
                'allocations.costCenter:id,uuid,name',
                'allocations.category:id,uuid,name,color,type',
                'allocations.subcategory:id,uuid,name',
                'allocations.company:id,uuid,name',
            ])
            ->withSum('settlements', 'value')
            ->where('uuid', $uuid)
            ->firstOrFail();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return list<FinancialAccount>
     */
    public function create(array $data): array
    {
        $installments = $data['installments'] ?? null;
        $allocations = $data['allocations'] ?? null;
        unset($data['installments'], $data['allocations']);

        if (! empty($data['credit_card_id'])) {
            return $this->createCardPurchase($data, $installments, $allocations);
        }

        if ($installments === null || (int) ($installments['quantity'] ?? 1) <= 1) {
            return [$this->persistAccount($data, $allocations)];
        }

        return $this->createInstallments($data, (int) $installments['quantity'], $installments['interval'] ?? 'monthly', $allocations);
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array{quantity?: int, interval?: string}|null  $installments
     * @param  list<array<string, mixed>>|null  $allocations
     * @return list<FinancialAccount>
     */
    private function createCardPurchase(array $data, ?array $installments, ?array $allocations): array
    {
        $card = CreditCard::query()->where('uuid', $data['credit_card_id'])->firstOrFail();

        $quantity = max(1, (int) ($installments['quantity'] ?? 1));

        return $this->creditCards->createPurchase($card, [
            'description' => $data['description'],
            'counterparty' => $data['counterparty'] ?? null,
            'company_id' => $data['company_id'] ?? null,
            'cost_center_id' => $data['cost_center_id'] ?? null,
            'category_id' => $data['category_id'] ?? null,
            'subcategory_id' => $data['subcategory_id'] ?? null,
            'value' => $data['value'],
            'purchase_date' => $data['purchase_date'],
            'observation' => $data['observation'] ?? null,
            'installments' => $quantity > 1 ? ['quantity' => $quantity] : null,
            'allocations' => $allocations,
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  list<array<string, mixed>>|null  $allocations
     */
    private function persistAccount(array $data, ?array $allocations = null): FinancialAccount
    {
        unset($data['allocations']);

        return DB::transaction(function () use ($data, $allocations): FinancialAccount {
            $data['allocation_mode'] ??= AllocationMode::Single;
            $account = FinancialAccount::query()->create($data);
            $this->allocations->sync($account, $allocations);

            return $account->refresh()->load([
                'bankAccount',
                'company',
                'costCenter',
                'allocations.costCenter:id,uuid,name',
                'allocations.category:id,uuid,name,color,type',
                'allocations.subcategory:id,uuid,name',
            ]);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  list<array<string, mixed>>|null  $allocations
     * @return list<FinancialAccount>
     */
    private function createInstallments(array $data, int $quantity, string $interval, ?array $allocations = null): array
    {
        if ($quantity < 1 || $quantity > 120) {
            throw new InvalidArgumentException('A quantidade de parcelas deve estar entre 1 e 120.');
        }

        $group = (string) Str::uuid();
        $total = round((float) $data['value'], 2);
        $installmentValue = round($total / $quantity, 2);
        $firstDueDate = DateOnly::parse($data['due_date']);
        $accounts = [];

        $accumulated = 0.0;
        $installmentValues = [];

        for ($i = 1; $i <= $quantity; $i++) {
            $value = $i === $quantity
                ? round($total - $accumulated, 2)
                : $installmentValue;

            $accumulated = round($accumulated + $value, 2);
            $installmentValues[] = $value;
        }

        $distributed = is_array($allocations) && $allocations !== []
            ? $this->allocations->distributeAcross($allocations, $installmentValues)
            : array_fill(0, $quantity, $allocations);

        for ($i = 1; $i <= $quantity; $i++) {
            $value = $installmentValues[$i - 1];

            $dueDate = $i === 1
                ? $firstDueDate->copy()
                : $this->nextDueDate($firstDueDate, $interval, $i - 1);

            $accounts[] = $this->persistAccount([
                ...$data,
                'value' => $value,
                'due_date' => DateOnly::normalize($dueDate),
                'purchase_date' => DateOnly::normalize($data['purchase_date'] ?? null),
                'installment_group_id' => $group,
                'installment_number' => $i,
                'installment_total' => $quantity,
            ], $distributed[$i - 1] ?? null);
        }

        return $accounts;
    }

    private function nextDueDate(Carbon $first, string $interval, int $step): Carbon
    {
        return match ($interval) {
            'daily' => $first->copy()->addDays($step),
            'weekly' => $first->copy()->addWeeks($step),
            default => $first->copy()->addMonthsNoOverflow($step),
        };
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(FinancialAccount $account, array $data): FinancialAccount
    {
        $hasSettlements = $account->settlements()->exists();

        $allocationsProvided = array_key_exists('allocations', $data);
        $allocations = $allocationsProvided ? $data['allocations'] : null;
        unset($data['allocations']);

        if ($account->is_card_purchase) {
            unset($data['bank_account_id'], $data['type'], $data['credit_card_id']);

            if (array_key_exists('due_date', $data) && $data['due_date'] === null) {
                unset($data['due_date']);
            }
        }

        $paidDateProvided = array_key_exists('paid_date', $data);
        $paidDate = $data['paid_date'] ?? null;
        unset($data['paid_date']);

        $valueChanged = array_key_exists('value', $data);

        $account->fill($data);
        $account->save();

        if ($allocationsProvided) {
            $this->allocations->sync($account->refresh(), is_array($allocations) ? $allocations : null);
        }

        if ($hasSettlements && $valueChanged) {
            $this->syncSettlementValues($account);
        }

        if ($paidDateProvided) {
            $this->syncSettlementDate($account, is_string($paidDate) ? $paidDate : null);
        }

        $this->recomputeStatus($account->refresh());

        return $account->refresh();
    }

    public function delete(FinancialAccount $account): void
    {
        $account->delete();
    }

    /**
     * @param  array{value?: ?numeric, settled_at?: ?string, method?: ?string}  $data
     */
    public function settle(FinancialAccount $account, User $user, array $data): Settlement
    {
        if ($account->is_card_purchase && ! $account->is_card_invoice_payable) {
            throw new InvalidArgumentException(
                'Compras no cartão são liquidadas pelo pagamento da fatura. Concilie ou baixe a conta da fatura.',
            );
        }

        $remaining = $account->remaining_amount;

        if ($remaining <= 0) {
            throw new InvalidArgumentException('A conta já está totalmente liquidada.');
        }

        $value = $data['value'] ?? null;
        $value = $value !== null ? round((float) $value, 2) : $remaining;

        if ($value <= 0 || $value > round($remaining + 0.001, 2)) {
            throw new InvalidArgumentException('O valor da baixa é inválido para esta conta.');
        }

        $settlement = Settlement::query()->create([
            'account_id' => $account->getKey(),
            'value' => $value,
            'settled_at' => $data['settled_at'] ?? now()->toDateString(),
            'method' => $data['method'] ?? null,
            'user_id' => $user->getKey(),
        ]);

        $this->recomputeStatus($account);
        $this->syncCardInvoicePaymentState($account->refresh());

        return $settlement;
    }

    public function unsettle(FinancialAccount $account, Settlement $settlement): void
    {
        DB::transaction(function () use ($account, $settlement): void {
            if ($settlement->reconciliation_id !== null) {
                $reconciliation = Reconciliation::query()
                    ->whereKey($settlement->reconciliation_id)
                    ->first();

                if ($reconciliation !== null) {
                    // C1/C2: baixa de conciliação desfaz o grupo inteiro do extrato (1→N incluso).
                    $this->reverseBankTransaction((int) $reconciliation->bank_transaction_id);

                    return;
                }
            }

            $settlement->delete();
            $this->recomputeStatus($account);
            $this->syncCardInvoicePaymentState($account->refresh());
        });
    }

    /**
     * Reabre a conta removendo todas as baixas e desfazendo vínculos de conciliação ativos.
     *
     * @return array{account: FinancialAccount, settlements_removed: int, reversed_amount: float, reconciliations_reversed: int}
     */
    public function reopen(FinancialAccount $account): array
    {
        if ($account->status === AccountStatus::Cancelled) {
            throw new InvalidArgumentException('Contas canceladas não podem ser reabertas.');
        }

        $settlements = $account->settlements()->get();

        if ($settlements->isEmpty()) {
            throw new InvalidArgumentException('Esta conta não possui baixas para reverter.');
        }

        $reversedAmount = round((float) $settlements->sum('value'), 2);
        $settlementsRemoved = $settlements->count();

        return DB::transaction(function () use ($account, $settlements, $reversedAmount, $settlementsRemoved): array {
            $transactionIds = Reconciliation::query()
                ->whereIn('id', $settlements->pluck('reconciliation_id')->filter()->unique()->values())
                ->pluck('bank_transaction_id')
                ->unique()
                ->values();

            $reconciliationsReversed = 0;

            foreach ($transactionIds as $transactionId) {
                $reconciliationsReversed += $this->reverseBankTransaction((int) $transactionId);
            }

            // Baixas manuais remanescentes nesta conta (sem vínculo de conciliação).
            $manualRemaining = $account->settlements()->count();
            if ($manualRemaining > 0) {
                $account->settlements()->delete();
            }

            $this->markUnreconciled($account);
            $this->recomputeStatus($account->refresh());
            $this->syncCardInvoicePaymentState($account);

            return [
                'account' => $account->refresh(),
                'settlements_removed' => $settlementsRemoved,
                'reversed_amount' => $reversedAmount,
                'reconciliations_reversed' => $reconciliationsReversed,
            ];
        });
    }

    /**
     * Desfaz todas as conciliações ativas de um extrato e reabre as contas vinculadas.
     */
    public function reverseBankTransaction(int|BankTransaction $transaction): int
    {
        $transaction = $transaction instanceof BankTransaction
            ? $transaction
            : BankTransaction::query()->findOrFail($transaction);

        return DB::transaction(function () use ($transaction): int {
            $reconciliations = Reconciliation::query()
                ->where('bank_transaction_id', $transaction->getKey())
                ->whereNull('reversed_at')
                ->get();

            if ($reconciliations->isEmpty()) {
                return 0;
            }

            foreach ($reconciliations as $reconciliation) {
                Settlement::query()
                    ->where('reconciliation_id', $reconciliation->getKey())
                    ->delete();

                $linkedAccount = FinancialAccount::query()->find($reconciliation->account_id);

                if ($linkedAccount !== null) {
                    $this->markUnreconciled($linkedAccount);
                    $this->recomputeStatus($linkedAccount);
                    $this->syncCardInvoicePaymentState($linkedAccount->refresh());
                }

                $reconciliation->reversed_at = now();
                $reconciliation->save();
            }

            if ($transaction->status === 'matched') {
                $transaction->status = 'pending';
                $transaction->save();
            }

            return $reconciliations->count();
        });
    }

    /**
     * Quando a conta a pagar da fatura é quitada (ou reaberta), propaga o status às compras.
     */
    public function syncCardInvoicePaymentState(FinancialAccount $account): void
    {
        if (! $account->is_card_invoice_payable) {
            return;
        }

        $invoice = CreditCardInvoice::query()
            ->where('financial_account_id', $account->getKey())
            ->first();

        if ($invoice === null) {
            return;
        }

        $account->unsetRelation('settlements');
        unset($account->settlements_sum_value);
        $account->loadSum('settlements', 'value');

        if ($account->status === AccountStatus::Settled) {
            $settledAt = $account->paid_date?->toDateString()
                ?? $account->settlements()->max('settled_at')
                ?? now()->toDateString();
            $userId = $account->settlements()->orderByDesc('id')->value('user_id');

            $this->creditCards->settleInvoicePurchases($invoice, $settledAt, $userId);
            $this->creditCards->markInvoicePaid($invoice);

            return;
        }

        $this->creditCards->unsettleInvoicePurchases($invoice);
        $this->creditCards->markInvoiceClosed($invoice);
    }

    public function cancel(FinancialAccount $account): FinancialAccount
    {
        $account->status = AccountStatus::Cancelled;
        $account->save();

        return $account->refresh();
    }

    public function recomputeStatus(FinancialAccount $account): void
    {
        if ($account->status === AccountStatus::Cancelled) {
            return;
        }

        $settled = round((float) $account->settlements()->sum('value'), 2);

        $status = match (true) {
            $settled <= 0 => AccountStatus::Open,
            $settled >= (float) $account->value => AccountStatus::Settled,
            default => AccountStatus::Partial,
        };

        $account->status = $status;
        $account->paid_date = $settled > 0
            ? $account->settlements()->max('settled_at')
            : null;
        $account->save();
    }

    private function syncSettlementValues(FinancialAccount $account): void
    {
        $settlements = $account->settlements()
            ->orderBy('settled_at')
            ->orderBy('id')
            ->get();

        if ($settlements->isEmpty()) {
            return;
        }

        $accountValue = round((float) $account->value, 2);

        if ($settlements->count() === 1) {
            $settlement = $settlements->first();
            $settlement->value = $accountValue;
            $settlement->save();

            return;
        }

        $previous = $settlements->slice(0, -1);
        $last = $settlements->last();

        $sumPrevious = round((float) $previous->sum('value'), 2);
        $newLastValue = round($accountValue - $sumPrevious, 2);

        if ($newLastValue < 0) {
            throw new AccountUpdateException(
                'value',
                'O valor não pode ser menor que o total já baixado nas baixas anteriores.',
            );
        }

        $last->value = $newLastValue;
        $last->save();
    }

    private function syncSettlementDate(FinancialAccount $account, ?string $paidDate): void
    {
        $settlement = $this->latestSettlement($account);

        if ($settlement === null) {
            if (filled($paidDate)) {
                throw new AccountUpdateException('paid_date', 'Não há baixa registrada para esta conta.');
            }

            return;
        }

        if (! filled($paidDate)) {
            throw new AccountUpdateException('paid_date', 'Informe a data da baixa.');
        }

        $settlement->settled_at = $paidDate;
        $settlement->save();
    }

    private function latestSettlement(FinancialAccount $account): ?Settlement
    {
        return $account->settlements()
            ->orderByDesc('settled_at')
            ->orderByDesc('id')
            ->first();
    }

    public function markReconciled(FinancialAccount $account): void
    {
        $account->reconciled_at = now();
        $account->save();
    }

    public function markUnreconciled(FinancialAccount $account): void
    {
        $account->reconciled_at = null;
        $account->save();
    }
}
