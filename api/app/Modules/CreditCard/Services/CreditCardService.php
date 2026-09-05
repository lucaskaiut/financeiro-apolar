<?php

namespace App\Modules\CreditCard\Services;

use App\Modules\Account\Enums\AccountStatus;
use App\Modules\Account\Enums\AccountType;
use App\Modules\Account\Models\FinancialAccount;
use App\Modules\Account\Models\Settlement;
use App\Modules\CreditCard\Enums\CreditCardInvoiceStatus;
use App\Modules\CreditCard\Models\CreditCard;
use App\Modules\CreditCard\Models\CreditCardInvoice;
use App\Modules\Shared\Support\DateOnly;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class CreditCardService
{
    public function paginate(int $perPage = 15, ?string $search = null): LengthAwarePaginator
    {
        return CreditCard::query()
            ->with('bankAccount:id,uuid,name')
            ->when(filled($search), fn ($q) => $q->where('name', 'like', "%{$search}%"))
            ->orderBy('name')
            ->paginate(min(max($perPage, 1), 100));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): CreditCard
    {
        return CreditCard::query()->create($data);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(CreditCard $creditCard, array $data): CreditCard
    {
        $creditCard->fill($data);
        $creditCard->save();

        return $creditCard->refresh();
    }

    public function delete(CreditCard $creditCard): void
    {
        $creditCard->delete();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return list<FinancialAccount>
     */
    public function createPurchase(CreditCard $creditCard, array $data): array
    {
        return DB::transaction(function () use ($creditCard, $data): array {
            $installments = $data['installments'] ?? null;
            unset($data['installments'], $data['allocations']);

            $quantity = max(1, (int) ($installments['quantity'] ?? 1));
            $purchaseDate = DateOnly::parse($data['purchase_date']);
            unset($data['purchase_date'], $data['due_date']);

            $base = [
                ...$data,
                'type' => AccountType::Payable,
                'credit_card_id' => $creditCard->uuid,
                'bank_account_id' => null,
                'is_card_purchase' => true,
                'is_card_invoice_payable' => false,
                'status' => AccountStatus::Open,
            ];

            if ($quantity <= 1) {
                [$dueDate] = $this->resolvePurchaseDates($creditCard, $purchaseDate);

                return [$this->persistPurchase([
                    ...$base,
                    'purchase_date' => $purchaseDate->toDateString(),
                    'due_date' => $dueDate->toDateString(),
                ])];
            }

            return $this->createPurchaseInstallments(
                $creditCard,
                $base,
                $purchaseDate,
                $quantity,
            );
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function persistPurchase(array $data): FinancialAccount
    {
        $account = FinancialAccount::query()->create($data);

        return $account->load(['company', 'costCenter']);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return list<FinancialAccount>
     */
    private function createPurchaseInstallments(
        CreditCard $creditCard,
        array $data,
        Carbon $firstPurchaseDate,
        int $quantity,
    ): array {
        if ($quantity < 1 || $quantity > 120) {
            throw new InvalidArgumentException('A quantidade de parcelas deve estar entre 1 e 120.');
        }

        $group = (string) Str::uuid();
        $total = round((float) $data['value'], 2);
        $installmentValue = round($total / $quantity, 2);
        $description = (string) $data['description'];
        $accounts = [];
        $accumulated = 0.0;

        [, $firstReferenceMonth] = $this->resolvePurchaseDates($creditCard, $firstPurchaseDate);
        $purchaseDate = $firstPurchaseDate->toDateString();

        for ($i = 1; $i <= $quantity; $i++) {
            $value = $i === $quantity
                ? round($total - $accumulated, 2)
                : $installmentValue;

            $accumulated = round($accumulated + $value, 2);

            $referenceMonth = Carbon::createFromFormat('Y-m', $firstReferenceMonth)
                ->addMonthsNoOverflow($i - 1)
                ->format('Y-m');
            [, $dueDate] = $this->resolveInvoiceDates($creditCard, $referenceMonth);

            $accounts[] = $this->persistPurchase([
                ...$data,
                'description' => "{$description} ({$i}/{$quantity})",
                'value' => $value,
                'purchase_date' => $purchaseDate,
                'due_date' => $dueDate->toDateString(),
                'installment_group_id' => $group,
                'installment_number' => $i,
                'installment_total' => $quantity,
            ]);
        }

        return $accounts;
    }

    public function closeInvoice(CreditCard $creditCard, string $referenceMonth): CreditCardInvoice
    {
        return DB::transaction(function () use ($creditCard, $referenceMonth): CreditCardInvoice {
            $existing = CreditCardInvoice::query()
                ->where('credit_card_id', $creditCard->uuid)
                ->where('reference_month', $referenceMonth)
                ->first();

            if ($existing !== null) {
                throw new InvalidArgumentException('Já existe fatura para este período.');
            }

            [$closingDate, $dueDate] = $this->resolveInvoiceDates($creditCard, $referenceMonth);

            // Cada parcela entra na fatura pelo vencimento (a data da compra permanece a da compra original).
            $purchases = FinancialAccount::query()
                ->where('credit_card_id', $creditCard->uuid)
                ->where('is_card_purchase', true)
                ->whereNull('credit_card_invoice_id')
                ->whereIn('status', [AccountStatus::Open->value, AccountStatus::Partial->value])
                ->whereDate('due_date', $dueDate->toDateString())
                ->withSum('settlements', 'value')
                ->get();

            if ($purchases->isEmpty()) {
                throw new InvalidArgumentException('Não há compras pendentes para fechar a fatura.');
            }

            $total = round($purchases->sum(fn (FinancialAccount $purchase) => $purchase->remaining_amount), 2);

            if ($total <= 0) {
                throw new InvalidArgumentException('Não há saldo pendente nas compras para fechar a fatura.');
            }

            $payable = FinancialAccount::query()->create([
                'type' => AccountType::Payable,
                'description' => "Fatura {$creditCard->name} — {$referenceMonth}",
                'counterparty' => $creditCard->institution ?? $creditCard->name,
                'bank_account_id' => $creditCard->bank_account_id,
                'credit_card_id' => $creditCard->uuid,
                'value' => $total,
                'due_date' => $dueDate,
                'status' => AccountStatus::Open,
                'is_card_purchase' => false,
                'is_card_invoice_payable' => true,
            ]);

            $invoice = CreditCardInvoice::query()->create([
                'credit_card_id' => $creditCard->uuid,
                'reference_month' => $referenceMonth,
                'closing_date' => $closingDate,
                'due_date' => $dueDate,
                'total_value' => $total,
                'status' => CreditCardInvoiceStatus::Closed,
                'financial_account_id' => $payable->getKey(),
            ]);

            // Mantém o histórico das compras (centro de custo, categoria, etc.).
            // A liquidação ocorre só no pagamento/conciliação da fatura.
            foreach ($purchases as $purchase) {
                $purchase->update([
                    'credit_card_invoice_id' => $invoice->uuid,
                ]);
            }

            return $invoice->load([
                'payable',
                'purchases.costCenter:id,uuid,name',
                'purchases.category:id,uuid,name,color,type',
                'creditCard',
            ]);
        });
    }

    public function markInvoicePaid(CreditCardInvoice $invoice): CreditCardInvoice
    {
        if ($invoice->status === CreditCardInvoiceStatus::Paid) {
            return $invoice;
        }

        $invoice->update(['status' => CreditCardInvoiceStatus::Paid]);

        return $invoice->refresh();
    }

    public function markInvoiceClosed(CreditCardInvoice $invoice): CreditCardInvoice
    {
        if ($invoice->status === CreditCardInvoiceStatus::Closed) {
            return $invoice;
        }

        $invoice->update(['status' => CreditCardInvoiceStatus::Closed]);

        return $invoice->refresh();
    }

    /**
     * Liquida as compras da fatura quando a conta a pagar da fatura é quitada.
     */
    public function settleInvoicePurchases(CreditCardInvoice $invoice, string $settledAt, ?int $userId = null): void
    {
        $purchases = FinancialAccount::query()
            ->where('credit_card_invoice_id', $invoice->uuid)
            ->where('is_card_purchase', true)
            ->withSum('settlements', 'value')
            ->get();

        foreach ($purchases as $purchase) {
            $remaining = $purchase->remaining_amount;

            if ($remaining <= 0.004) {
                continue;
            }

            Settlement::query()->create([
                'account_id' => $purchase->getKey(),
                'value' => $remaining,
                'settled_at' => $settledAt,
                'method' => 'credit_card_invoice',
                'user_id' => $userId,
            ]);

            $purchase->unsetRelation('settlements');
            unset($purchase->settlements_sum_value);

            $settled = round((float) $purchase->settlements()->sum('value'), 2);
            $purchase->status = $settled >= (float) $purchase->value
                ? AccountStatus::Settled
                : ($settled > 0 ? AccountStatus::Partial : AccountStatus::Open);
            $purchase->paid_date = $settled > 0 ? $settledAt : null;
            $purchase->save();
        }
    }

    /**
     * Reverte liquidações de compras quando a fatura deixa de estar quitada.
     */
    public function unsettleInvoicePurchases(CreditCardInvoice $invoice): void
    {
        $purchases = FinancialAccount::query()
            ->where('credit_card_invoice_id', $invoice->uuid)
            ->where('is_card_purchase', true)
            ->get();

        foreach ($purchases as $purchase) {
            Settlement::query()
                ->where('account_id', $purchase->getKey())
                ->where('method', 'credit_card_invoice')
                ->delete();

            $purchase->unsetRelation('settlements');
            unset($purchase->settlements_sum_value);

            $settled = round((float) $purchase->settlements()->sum('value'), 2);
            $purchase->status = match (true) {
                $settled <= 0 => AccountStatus::Open,
                $settled >= (float) $purchase->value => AccountStatus::Settled,
                default => AccountStatus::Partial,
            };
            $purchase->paid_date = $settled > 0
                ? $purchase->settlements()->max('settled_at')
                : null;
            $purchase->save();
        }
    }

    /**
     * @return array{0: Carbon, 1: string}
     */
    private function resolvePurchaseDates(CreditCard $creditCard, Carbon $purchaseDate): array
    {
        $referenceMonth = $this->resolveReferenceMonth($creditCard, $purchaseDate);
        [, $dueDate] = $this->resolveInvoiceDates($creditCard, $referenceMonth);

        return [$dueDate, $referenceMonth];
    }

    private function resolveReferenceMonth(CreditCard $creditCard, Carbon $purchaseDate): string
    {
        $month = $purchaseDate->copy()->startOfMonth();
        $closingDay = (int) ($creditCard->closing_day ?? 1);
        $closingDate = $month->copy()->day(min($closingDay, $month->daysInMonth));

        if ($purchaseDate->greaterThan($closingDate)) {
            $month->addMonth();
        }

        return $month->format('Y-m');
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    private function resolveInvoiceDates(CreditCard $creditCard, string $referenceMonth): array
    {
        $month = Carbon::createFromFormat('Y-m', $referenceMonth)->startOfMonth();
        $closingDay = min((int) ($creditCard->closing_day ?? 1), $month->daysInMonth);
        $rawDueDay = (int) ($creditCard->due_day ?? $closingDay);

        $closingDate = $month->copy()->day($closingDay);

        // Se o vencimento é depois do fechamento no calendário (ex.: fecha 10, vence 17),
        // o pagamento ocorre no mesmo mês. Caso contrário (ex.: fecha 25, vence 5), no mês seguinte.
        if ($rawDueDay > $closingDay) {
            $dueDate = $month->copy()->day(min($rawDueDay, $month->daysInMonth));
        } else {
            $nextMonth = $month->copy()->addMonth();
            $dueDate = $nextMonth->copy()->day(min($rawDueDay, $nextMonth->daysInMonth));
        }

        return [$closingDate, $dueDate];
    }
}
