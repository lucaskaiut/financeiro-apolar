<?php

namespace App\Modules\CashFlow\Services;

use App\Modules\Account\Enums\AccountType;
use App\Modules\Account\Models\FinancialAccount;
use App\Modules\Account\Models\Settlement;
use App\Modules\BankAccount\Models\BankAccount;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class CashFlowService
{
    /**
     * @return array<string, mixed>
     */
    public function realized(?string $from = null, ?string $to = null, ?string $bankAccountId = null, ?string $categoryId = null): array
    {
        $fromDate = $from ? Carbon::parse($from)->startOfDay() : now()->startOfMonth();
        $toDate = $to ? Carbon::parse($to)->endOfDay() : now()->endOfMonth();

        $openingBalance = round(
            $this->initialBalance($bankAccountId)
            + $this->netSettled($fromDate->copy()->subSecond(), $bankAccountId, $categoryId),
            2,
        );

        $settlements = Settlement::query()
            ->with(['account.costCenter:id,uuid,name', 'account.category:id,uuid,name,type'])
            ->whereDate('settled_at', '>=', $fromDate->toDateString())
            ->whereDate('settled_at', '<=', $toDate->toDateString())
            ->where(function ($query): void {
                $query->whereNull('method')
                    ->orWhere('method', '!=', 'credit_card_invoice');
            })
            ->whereHas('account', fn ($a) => $a->where(function ($query): void {
                $query->where('is_card_purchase', false)
                    ->orWhere('is_card_invoice_payable', true);
            }))
            ->when($bankAccountId, fn ($q) => $q->whereHas('account', fn ($a) => $a->where('bank_account_id', $bankAccountId)))
            ->when($categoryId, fn ($q) => $q->whereHas('account', fn ($a) => $a->where('category_id', $categoryId)))
            ->orderBy('settled_at')
            ->get();

        $totalIn = 0.0;
        $totalOut = 0.0;
        $entries = [];

        foreach ($settlements as $settlement) {
            $account = $settlement->account;

            if ($account === null) {
                continue;
            }

            $isIn = $account->type === AccountType::Receivable;
            $value = (float) $settlement->value;

            if ($isIn) {
                $totalIn += $value;
            } else {
                $totalOut += $value;
            }

            $entries[] = [
                'id' => $settlement->uuid,
                'date' => $settlement->settled_at?->toDateString(),
                'description' => $account->description,
                'bank_account' => $account->costCenter?->name,
                'category' => $account->category?->name,
                'direction' => $isIn ? 'in' : 'out',
                'value' => $value,
                'is_transfer' => $account->transfer_id !== null,
            ];
        }

        return [
            'from' => $fromDate->toDateString(),
            'to' => $toDate->toDateString(),
            'opening_balance' => $openingBalance,
            'total_in' => round($totalIn, 2),
            'total_out' => round($totalOut, 2),
            'final_balance' => round($openingBalance + $totalIn - $totalOut, 2),
            'entries' => $entries,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function projected(?string $from = null, ?string $to = null, int $days = 30, ?string $bankAccountId = null, ?AccountType $accountType = null): array
    {
        if ($from !== null || $to !== null) {
            $fromDate = $from ? Carbon::parse($from)->startOfDay() : now()->startOfDay();
            $toDate = $to ? Carbon::parse($to)->endOfDay() : now()->addDays(min(max($days, 1), 365))->endOfDay();
        } else {
            $days = min(max($days, 1), 365);
            $fromDate = now()->startOfDay();
            $toDate = now()->addDays($days)->endOfDay();
        }

        if ($fromDate->gt($toDate)) {
            [$fromDate, $toDate] = [$toDate->copy()->startOfDay(), $fromDate->copy()->endOfDay()];
        }

        $periodDays = (int) $fromDate->copy()->startOfDay()->diffInDays($toDate->copy()->startOfDay());

        if ($periodDays > 365) {
            $toDate = $fromDate->copy()->addDays(365)->endOfDay();
            $periodDays = 365;
        }

        $base = fn () => FinancialAccount::query()
            ->with(['bankAccount:id,uuid,name', 'category:id,uuid,name,type'])
            ->withSum('settlements', 'value')
            ->whereIn('status', ['open', 'partial'])
            // Compras do cartão não afetam caixa bancário; só a fatura.
            ->where('is_card_purchase', false)
            ->whereDate('due_date', '>=', $fromDate->toDateString())
            ->whereDate('due_date', '<=', $toDate->toDateString())
            ->when($bankAccountId, fn ($q) => $q->where('bank_account_id', $bankAccountId))
            ->when($accountType, fn ($q) => $q->where('type', $accountType));

        $futureAccounts = $base()->whereNull('recurrence_id')->whereNull('transfer_id')->whereNull('installment_group_id')->get();
        $installments = $base()->whereNotNull('installment_group_id')->get();
        $recurrences = $base()->whereNotNull('recurrence_id')->get();

        $all = $futureAccounts->concat($installments)->concat($recurrences);

        $grouped = $all
            ->groupBy(fn (FinancialAccount $account) => $account->due_date->toDateString())
            ->map(fn (Collection $items) => [
                'in' => round($items->where('type', AccountType::Receivable)->sum('remaining_amount'), 2),
                'out' => round($items->where('type', AccountType::Payable)->sum('remaining_amount'), 2),
            ]);

        $balance = $this->currentBalance($bankAccountId);
        $series = [];

        for ($i = 0; $i <= $periodDays; $i++) {
            $date = $fromDate->copy()->addDays($i)->toDateString();
            $movement = $grouped->get($date, ['in' => 0.0, 'out' => 0.0]);
            $balance = round($balance + $movement['in'] - $movement['out'], 2);

            $series[] = [
                'date' => $date,
                'in' => $movement['in'],
                'out' => $movement['out'],
                'projected_balance' => $balance,
            ];
        }

        return [
            'from' => $fromDate->toDateString(),
            'to' => $toDate->toDateString(),
            'opening_balance' => $this->currentBalance($bankAccountId),
            'days' => $periodDays,
            'total_in' => round($all->where('type', AccountType::Receivable)->sum('remaining_amount'), 2),
            'total_out' => round($all->where('type', AccountType::Payable)->sum('remaining_amount'), 2),
            'series' => $series,
            'accounts' => $this->present($futureAccounts),
            'installments' => $this->present($installments),
            'recurrences' => $this->present($recurrences),
        ];
    }

    /**
     * @param  Collection<int, FinancialAccount>  $accounts
     * @return list<array<string, mixed>>
     */
    private function present(Collection $accounts): array
    {
        return $accounts->map(fn (FinancialAccount $account) => [
            'id' => $account->uuid,
            'description' => $account->description,
            'counterparty' => $account->counterparty,
            'bank_account' => $account->costCenter?->name,
            'category' => $account->category?->name,
            'direction' => $account->type === AccountType::Receivable ? 'in' : 'out',
            'value' => (float) $account->value,
            'remaining_amount' => $account->remaining_amount,
            'due_date' => $account->due_date->toDateString(),
            'installment' => $account->installment_total > 1 ? "{$account->installment_number}/{$account->installment_total}" : null,
        ])->values()->all();
    }

    private function initialBalance(?string $bankAccountId): float
    {
        $balance = BankAccount::query()
            ->when($bankAccountId, fn ($q) => $q->where('uuid', $bankAccountId))
            ->sum('initial_balance');

        return (float) $balance;
    }

    private function currentBalance(?string $bankAccountId): float
    {
        return round($this->initialBalance($bankAccountId) + $this->netSettled(now()->endOfDay(), $bankAccountId, null), 2);
    }

    private function netSettled(Carbon $upTo, ?string $bankAccountId, ?string $categoryId): float
    {
        $base = fn () => Settlement::query()
            ->whereDate('settled_at', '<=', $upTo->toDateString())
            ->where(function ($query): void {
                $query->whereNull('method')
                    ->orWhere('method', '!=', 'credit_card_invoice');
            })
            ->when($bankAccountId, fn ($q) => $q->whereHas('account', fn ($a) => $a->where('bank_account_id', $bankAccountId)))
            ->when($categoryId, fn ($q) => $q->whereHas('account', fn ($a) => $a->where('category_id', $categoryId)))
            ->whereHas('account', fn ($a) => $a->where(function ($query): void {
                $query->where('is_card_purchase', false)
                    ->orWhere('is_card_invoice_payable', true);
            }));

        $in = (float) $base()->whereHas('account', fn ($a) => $a->where('type', AccountType::Receivable->value))->sum('value');
        $out = (float) $base()->whereHas('account', fn ($a) => $a->where('type', AccountType::Payable->value))->sum('value');

        return round($in - $out, 2);
    }
}
