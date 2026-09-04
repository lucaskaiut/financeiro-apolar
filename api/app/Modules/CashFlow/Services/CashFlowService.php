<?php

namespace App\Modules\CashFlow\Services;

use App\Modules\Account\Enums\AccountType;
use App\Modules\Account\Models\FinancialAccount;
use App\Modules\Account\Models\Settlement;
use App\Modules\CostCenter\Models\CostCenter;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class CashFlowService
{
    /**
     * @return array<string, mixed>
     */
    public function realized(?string $from = null, ?string $to = null, ?string $costCenterId = null, ?string $categoryId = null): array
    {
        $fromDate = $from ? Carbon::parse($from)->startOfDay() : now()->startOfMonth();
        $toDate = $to ? Carbon::parse($to)->endOfDay() : now()->endOfMonth();

        $openingBalance = round(
            $this->initialBalance($costCenterId)
            + $this->netSettled($fromDate->copy()->subSecond(), $costCenterId, $categoryId),
            2,
        );

        $settlements = Settlement::query()
            ->with(['account.costCenter:id,uuid,name', 'account.category:id,uuid,name,type'])
            ->whereBetween('settled_at', [$fromDate, $toDate])
            ->when($costCenterId, fn ($q) => $q->whereHas('account', fn ($a) => $a->where('cost_center_id', $costCenterId)))
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
                'cost_center' => $account->costCenter?->name,
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
    public function projected(?string $from = null, ?string $to = null, int $days = 30, ?string $costCenterId = null, ?AccountType $accountType = null): array
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
            ->with(['costCenter:id,uuid,name', 'category:id,uuid,name,type'])
            ->withSum('settlements', 'value')
            ->whereIn('status', ['open', 'partial'])
            ->whereDate('due_date', '>=', $fromDate->toDateString())
            ->whereDate('due_date', '<=', $toDate->toDateString())
            ->when($costCenterId, fn ($q) => $q->where('cost_center_id', $costCenterId))
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

        $balance = $this->currentBalance($costCenterId);
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
            'opening_balance' => $this->currentBalance($costCenterId),
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
            'cost_center' => $account->costCenter?->name,
            'category' => $account->category?->name,
            'direction' => $account->type === AccountType::Receivable ? 'in' : 'out',
            'value' => (float) $account->value,
            'remaining_amount' => $account->remaining_amount,
            'due_date' => $account->due_date->toDateString(),
            'installment' => $account->installment_total > 1 ? "{$account->installment_number}/{$account->installment_total}" : null,
        ])->values()->all();
    }

    private function initialBalance(?string $costCenterId): float
    {
        $balance = CostCenter::query()
            ->when($costCenterId, fn ($q) => $q->where('uuid', $costCenterId))
            ->sum('initial_balance');

        return (float) $balance;
    }

    private function currentBalance(?string $costCenterId): float
    {
        return round($this->initialBalance($costCenterId) + $this->netSettled(now()->endOfDay(), $costCenterId, null), 2);
    }

    private function netSettled(Carbon $upTo, ?string $costCenterId, ?string $categoryId): float
    {
        $base = fn () => Settlement::query()
            ->where('settled_at', '<=', $upTo)
            ->when($costCenterId, fn ($q) => $q->whereHas('account', fn ($a) => $a->where('cost_center_id', $costCenterId)))
            ->when($categoryId, fn ($q) => $q->whereHas('account', fn ($a) => $a->where('category_id', $categoryId)));

        $in = (float) $base()->whereHas('account', fn ($a) => $a->where('type', AccountType::Receivable->value))->sum('value');
        $out = (float) $base()->whereHas('account', fn ($a) => $a->where('type', AccountType::Payable->value))->sum('value');

        return round($in - $out, 2);
    }
}
