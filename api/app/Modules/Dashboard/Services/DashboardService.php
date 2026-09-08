<?php

namespace App\Modules\Dashboard\Services;

use App\Modules\Account\Enums\AccountType;
use App\Modules\Account\Models\FinancialAccount;
use App\Modules\Account\Models\Settlement;
use App\Modules\Account\Support\ClassificationSlices;
use App\Modules\CashFlow\Services\CashFlowService;
use App\Modules\Category\Enums\CategoryType;
use App\Modules\BankAccount\Models\BankAccount;
use App\Modules\CostCenter\Models\CostCenter;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class DashboardService
{
    public function __construct(
        private readonly CashFlowService $cashFlow,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function summary(?string $costCenterId = null): array
    {
        $today = now()->startOfDay();
        $monthStart = now()->startOfMonth();
        $monthEnd = now()->endOfMonth();

        $openAccounts = FinancialAccount::query()
            ->with(array_merge(ClassificationSlices::withAccount(), ['bankAccount:id,uuid,name']))
            ->whereIn('status', ['open', 'partial'])
            ->countingFinancially()
            ->forCostCenter($costCenterId)
            ->withSum('settlements', 'value')
            ->get();

        $payableOpen = $openAccounts->where('type', AccountType::Payable);
        $receivableOpen = $openAccounts->where('type', AccountType::Receivable);

        $overdue = $openAccounts
            ->where('due_date', '<', $today)
            ->sortBy('due_date');

        $projected = $this->cashFlow->projected(days: 7, costCenterId: $costCenterId);
        $finalProjectedBalance = $projected['series'][count($projected['series']) - 1]['projected_balance'] ?? null;

        $remainingOf = fn (FinancialAccount $account): float => ClassificationSlices::amountMatching(
            $account,
            round((float) $account->value - (float) ($account->settlements_sum_value ?? 0), 2),
            $costCenterId,
        );

        return [
            'cost_centers' => $this->costCenters(),
            'selected_cost_center_id' => $costCenterId,
            'kpis' => [
                'current_balance' => $this->currentBalance($costCenterId),
                'month_income' => $this->settledBetween($monthStart, $monthEnd, AccountType::Receivable, $costCenterId),
                'month_expense' => $this->settledBetween($monthStart, $monthEnd, AccountType::Payable, $costCenterId),
                'month_result' => round(
                    $this->settledBetween($monthStart, $monthEnd, AccountType::Receivable, $costCenterId)
                    - $this->settledBetween($monthStart, $monthEnd, AccountType::Payable, $costCenterId),
                    2,
                ),
                'receivable_open' => round($receivableOpen->sum($remainingOf), 2),
                'payable_open' => round($payableOpen->sum($remainingOf), 2),
                'overdue_total' => round($overdue->sum($remainingOf), 2),
                'overdue_count' => $overdue->count(),
                'projected_7d' => round($projected['total_in'] - $projected['total_out'], 2),
                'projected_balance' => $finalProjectedBalance,
            ],
            'cash_flow_series' => $this->monthlyCashFlowSeries($costCenterId),
            'projected_series' => $projected['series'],
            'expense_by_category' => $this->byCategory($monthStart, $monthEnd, CategoryType::Expense, $costCenterId),
            'income_by_category' => $this->byCategory($monthStart, $monthEnd, CategoryType::Income, $costCenterId),
            'balance_by_bank_account' => $this->balanceByBankAccount($costCenterId),
            'overdue' => $this->presentAccounts($overdue->take(8), $costCenterId),
            'upcoming' => $this->presentAccounts(
                $openAccounts
                    ->where('due_date', '>=', $today)
                    ->where('due_date', '<=', now()->addDays(30)->endOfDay())
                    ->sortBy('due_date')
                    ->take(8),
                $costCenterId,
            ),
            'payables_next_7d' => $this->presentAccounts(
                $payableOpen
                    ->where('due_date', '>=', $today)
                    ->where('due_date', '<=', now()->addDays(7)->endOfDay())
                    ->sortBy('due_date')
                    ->values(),
                $costCenterId,
            ),
            'payables_next_7d_total' => round(
                $payableOpen
                    ->where('due_date', '>=', $today)
                    ->where('due_date', '<=', now()->addDays(7)->endOfDay())
                    ->sum($remainingOf),
                2,
            ),
        ];
    }

    /**
     * @return list<array{id: string, name: string}>
     */
    private function costCenters(): array
    {
        return CostCenter::query()
            ->where('status', 'active')
            ->orderBy('name')
            ->get(['uuid', 'name'])
            ->map(fn (CostCenter $costCenter) => ['id' => $costCenter->uuid, 'name' => $costCenter->name])
            ->all();
    }

    private function currentBalance(?string $costCenterId): float
    {
        $initial = $costCenterId
            ? 0.0
            : (float) BankAccount::query()->sum('initial_balance');

        return round($initial + $this->netSettledAmount(null, $costCenterId), 2);
    }

    private function settledBetween(Carbon $from, Carbon $to, AccountType $type, ?string $costCenterId): float
    {
        $settlements = Settlement::query()
            ->countingFinancially()
            ->whereDate('settled_at', '>=', $from->toDateString())
            ->whereDate('settled_at', '<=', $to->toDateString())
            ->whereHas('account', fn ($q) => $q->where('type', $type->value))
            ->forCostCenter($costCenterId)
            ->with(['account' => fn ($q) => $q->with(ClassificationSlices::withAccount())])
            ->get();

        return round(
            $settlements->sum(fn (Settlement $settlement) => $settlement->account === null
                ? 0.0
                : ClassificationSlices::amountMatching($settlement->account, (float) $settlement->value, $costCenterId)),
            2,
        );
    }

    /**
     * @return list<array{month: string, label: string, income: float, expense: float, balance: float}>
     */
    private function monthlyCashFlowSeries(?string $costCenterId): array
    {
        $windowStart = now()->startOfMonth()->subMonths(11);

        $settlements = Settlement::query()
            ->countingFinancially()
            ->with(['account' => fn ($q) => $q->with(ClassificationSlices::withAccount())])
            ->whereDate('settled_at', '>=', $windowStart->toDateString())
            ->forCostCenter($costCenterId)
            ->get();

        $balance = round(
            ($costCenterId ? 0.0 : (float) BankAccount::query()->sum('initial_balance'))
            + $this->netSettledAmount($windowStart->copy()->subSecond(), $costCenterId),
            2,
        );

        $labels = ['jan', 'fev', 'mar', 'abr', 'mai', 'jun', 'jul', 'ago', 'set', 'out', 'nov', 'dez'];

        $series = [];

        for ($i = 0; $i < 12; $i++) {
            $month = $windowStart->copy()->addMonths($i);
            $key = $month->format('Y-m');

            $items = $settlements->filter(fn (Settlement $s) => $s->settled_at->format('Y-m') === $key);

            $income = round($items
                ->filter(fn (Settlement $s) => $s->account?->type === AccountType::Receivable)
                ->sum(fn (Settlement $s) => $s->account === null
                    ? 0.0
                    : ClassificationSlices::amountMatching($s->account, (float) $s->value, $costCenterId)), 2);
            $expense = round($items
                ->filter(fn (Settlement $s) => $s->account?->type === AccountType::Payable)
                ->sum(fn (Settlement $s) => $s->account === null
                    ? 0.0
                    : ClassificationSlices::amountMatching($s->account, (float) $s->value, $costCenterId)), 2);
            $balance = round($balance + $income - $expense, 2);

            $series[] = [
                'month' => $key,
                'label' => $labels[$month->month - 1],
                'income' => $income,
                'expense' => $expense,
                'balance' => $balance,
            ];
        }

        return $series;
    }

    private function netSettledAmount(?Carbon $upTo, ?string $costCenterId): float
    {
        $query = Settlement::query()
            ->countingFinancially()
            ->forCostCenter($costCenterId)
            ->with(['account' => fn ($q) => $q->with(ClassificationSlices::withAccount())]);

        if ($upTo !== null) {
            $query->whereDate('settled_at', '<=', $upTo->toDateString());
        }

        $in = 0.0;
        $out = 0.0;

        foreach ($query->get() as $settlement) {
            $account = $settlement->account;

            if ($account === null) {
                continue;
            }

            $amount = ClassificationSlices::amountMatching($account, (float) $settlement->value, $costCenterId);

            if ($account->type === AccountType::Receivable) {
                $in += $amount;
            } else {
                $out += $amount;
            }
        }

        return round($in - $out, 2);
    }

    /**
     * @return list<array{category: string, total: float}>
     */
    private function byCategory(Carbon $from, Carbon $to, CategoryType $type, ?string $costCenterId): array
    {
        $settlements = Settlement::query()
            ->countingFinancially()
            ->forCostCenter($costCenterId)
            ->with(['account' => fn ($q) => $q->with(ClassificationSlices::withAccount())])
            ->whereDate('settled_at', '>=', $from->toDateString())
            ->whereDate('settled_at', '<=', $to->toDateString())
            ->get();

        $totals = [];

        foreach ($settlements as $settlement) {
            $account = $settlement->account;

            if ($account === null) {
                continue;
            }

            foreach (ClassificationSlices::forAmount($account, (float) $settlement->value) as $slice) {
                if ($slice['category_type'] !== $type->value || $slice['category_name'] === null) {
                    continue;
                }

                if ($costCenterId && $slice['cost_center_id'] !== $costCenterId) {
                    continue;
                }

                $name = $slice['category_name'];
                $totals[$name] = round(($totals[$name] ?? 0) + $slice['value'], 2);
            }
        }

        arsort($totals);

        return array_map(
            fn (string $name, float $total) => ['category' => $name, 'total' => $total],
            array_keys($totals),
            array_values($totals),
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function balanceByBankAccount(?string $costCenterId): array
    {
        $banks = BankAccount::query()->orderBy('name')->get();
        $rows = [];

        foreach ($banks as $bank) {
            $settlements = Settlement::query()
                ->countingFinancially()
                ->forBankAccount($bank->uuid)
                ->forCostCenter($costCenterId)
                ->with(['account' => fn ($q) => $q->with(ClassificationSlices::withAccount())])
                ->get();

            $income = 0.0;
            $expense = 0.0;

            foreach ($settlements as $settlement) {
                $account = $settlement->account;

                if ($account === null) {
                    continue;
                }

                $amount = ClassificationSlices::amountMatching($account, (float) $settlement->value, $costCenterId);

                if ($account->type === AccountType::Receivable) {
                    $income += $amount;
                } else {
                    $expense += $amount;
                }
            }

            $initial = $costCenterId ? 0.0 : (float) $bank->initial_balance;

            $rows[] = [
                'bank_account_id' => $bank->uuid,
                'bank_account' => $bank->name,
                'initial_balance' => $initial,
                'income' => round($income, 2),
                'expense' => round($expense, 2),
                'balance' => round($initial + $income - $expense, 2),
            ];
        }

        return $rows;
    }

    /**
     * @param  Collection<int, FinancialAccount>  $accounts
     * @return list<array<string, mixed>>
     */
    private function presentAccounts($accounts, ?string $costCenterId = null): array
    {
        return $accounts
            ->map(function (FinancialAccount $a) use ($costCenterId) {
                $remaining = round((float) $a->value - (float) ($a->settlements_sum_value ?? 0), 2);
                $category = $a->isSplit()
                    ? $a->allocations
                        ->map(fn ($allocation) => $allocation->category?->name)
                        ->filter()
                        ->unique()
                        ->implode(' / ')
                    : $a->category?->name;

                return [
                    'id' => $a->uuid,
                    'description' => $a->description,
                    'counterparty' => $a->counterparty,
                    'type' => $a->type->value,
                    'bank_account' => $a->bankAccount?->name,
                    'category' => $category !== '' ? $category : $a->category?->name,
                    'value' => (float) $a->value,
                    'remaining_amount' => ClassificationSlices::amountMatching($a, $remaining, $costCenterId),
                    'due_date' => $a->due_date->toDateString(),
                ];
            })
            ->values()
            ->all();
    }
}
