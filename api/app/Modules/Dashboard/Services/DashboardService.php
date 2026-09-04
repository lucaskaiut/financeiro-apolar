<?php

namespace App\Modules\Dashboard\Services;

use App\Modules\Account\Enums\AccountType;
use App\Modules\Account\Models\FinancialAccount;
use App\Modules\Account\Models\Settlement;
use App\Modules\CashFlow\Services\CashFlowService;
use App\Modules\Category\Enums\CategoryType;
use App\Modules\CostCenter\Models\CostCenter;
use App\Modules\Report\Services\ReportService;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class DashboardService
{
    public function __construct(
        private readonly CashFlowService $cashFlow,
        private readonly ReportService $reports,
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
            ->with(['costCenter:id,uuid,name', 'category:id,uuid,name,type'])
            ->whereIn('status', ['open', 'partial'])
            ->when($costCenterId, fn ($q) => $q->where('cost_center_id', $costCenterId))
            ->withSum('settlements', 'value')
            ->get();

        $payableOpen = $openAccounts->where('type', AccountType::Payable);
        $receivableOpen = $openAccounts->where('type', AccountType::Receivable);

        $overdue = $openAccounts
            ->where('due_date', '<', $today)
            ->sortBy('due_date');

        $projected = $this->cashFlow->projected(null, null, 7, $costCenterId);
        $finalProjectedBalance = $projected['series'][count($projected['series']) - 1]['projected_balance'] ?? null;

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
                'receivable_open' => round($receivableOpen->sum(fn (FinancialAccount $a) => $a->value - $a->settlements_sum_value), 2),
                'payable_open' => round($payableOpen->sum(fn (FinancialAccount $a) => $a->value - $a->settlements_sum_value), 2),
                'overdue_total' => round($overdue->sum(fn (FinancialAccount $a) => $a->value - $a->settlements_sum_value), 2),
                'overdue_count' => $overdue->count(),
                'projected_7d' => round($projected['total_in'] - $projected['total_out'], 2),
                'projected_balance' => $finalProjectedBalance,
            ],
            'cash_flow_series' => $this->monthlyCashFlowSeries($costCenterId),
            'projected_series' => $projected['series'],
            'expense_by_category' => $this->byCategory($monthStart, $monthEnd, CategoryType::Expense, $costCenterId),
            'income_by_category' => $this->byCategory($monthStart, $monthEnd, CategoryType::Income, $costCenterId),
            'balance_by_cost_center' => $this->balanceByCostCenter($costCenterId),
            'overdue' => $this->presentAccounts($overdue->take(8)),
            'upcoming' => $this->presentAccounts(
                $openAccounts
                    ->where('due_date', '>=', $today)
                    ->where('due_date', '<=', now()->addDays(30)->endOfDay())
                    ->sortBy('due_date')
                    ->take(8),
            ),
            'payables_next_7d' => $this->presentAccounts(
                $payableOpen
                    ->where('due_date', '>=', $today)
                    ->where('due_date', '<=', now()->addDays(7)->endOfDay())
                    ->sortBy('due_date')
                    ->values(),
            ),
            'payables_next_7d_total' => round(
                $payableOpen
                    ->where('due_date', '>=', $today)
                    ->where('due_date', '<=', now()->addDays(7)->endOfDay())
                    ->sum(fn (FinancialAccount $a) => $a->value - $a->settlements_sum_value),
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
            ->orderBy('name')
            ->get(['uuid', 'name'])
            ->map(fn (CostCenter $cc) => ['id' => $cc->uuid, 'name' => $cc->name])
            ->all();
    }

    private function currentBalance(?string $costCenterId): float
    {
        $initial = (float) CostCenter::query()
            ->when($costCenterId, fn ($q) => $q->where('uuid', $costCenterId))
            ->sum('initial_balance');

        $base = fn () => Settlement::query()
            ->when($costCenterId, fn ($q) => $q->whereHas('account', fn ($a) => $a->where('cost_center_id', $costCenterId)));

        $in = (float) $base()->whereHas('account', fn ($q) => $q->where('type', AccountType::Receivable->value))->sum('value');
        $out = (float) $base()->whereHas('account', fn ($q) => $q->where('type', AccountType::Payable->value))->sum('value');

        return round($initial + $in - $out, 2);
    }

    private function settledBetween(Carbon $from, Carbon $to, AccountType $type, ?string $costCenterId): float
    {
        return round(
            (float) Settlement::query()
                ->whereBetween('settled_at', [$from, $to])
                ->whereHas('account', fn ($q) => $q->where('type', $type->value))
                ->when($costCenterId, fn ($q) => $q->whereHas('account', fn ($a) => $a->where('cost_center_id', $costCenterId)))
                ->sum('value'),
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
            ->with('account:id,type,cost_center_id')
            ->whereDate('settled_at', '>=', $windowStart->toDateString())
            ->when($costCenterId, fn ($q) => $q->whereHas('account', fn ($a) => $a->where('cost_center_id', $costCenterId)))
            ->get();

        $balance = round(
            (float) CostCenter::query()
                ->when($costCenterId, fn ($q) => $q->where('uuid', $costCenterId))
                ->sum('initial_balance')
            + $this->netSettledBefore($windowStart, $costCenterId),
            2,
        );

        $labels = ['jan', 'fev', 'mar', 'abr', 'mai', 'jun', 'jul', 'ago', 'set', 'out', 'nov', 'dez'];

        $series = [];

        for ($i = 0; $i < 12; $i++) {
            $month = $windowStart->copy()->addMonths($i);
            $key = $month->format('Y-m');

            $items = $settlements->filter(fn (Settlement $s) => $s->settled_at->format('Y-m') === $key);

            $income = round((float) $items->filter(fn (Settlement $s) => $s->account?->type === AccountType::Receivable)->sum('value'), 2);
            $expense = round((float) $items->filter(fn (Settlement $s) => $s->account?->type === AccountType::Payable)->sum('value'), 2);
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

    private function netSettledBefore(Carbon $upTo, ?string $costCenterId): float
    {
        $base = fn () => Settlement::query()
            ->whereDate('settled_at', '<', $upTo->toDateString())
            ->when($costCenterId, fn ($q) => $q->whereHas('account', fn ($a) => $a->where('cost_center_id', $costCenterId)));

        $in = (float) $base()->whereHas('account', fn ($q) => $q->where('type', AccountType::Receivable->value))->sum('value');
        $out = (float) $base()->whereHas('account', fn ($q) => $q->where('type', AccountType::Payable->value))->sum('value');

        return round($in - $out, 2);
    }

    /**
     * @return list<array{category: string, total: float}>
     */
    private function byCategory(Carbon $from, Carbon $to, CategoryType $type, ?string $costCenterId): array
    {
        $settlements = Settlement::query()
            ->with('account.category:id,uuid,name,type')
            ->whereBetween('settled_at', [$from, $to])
            ->when($costCenterId, fn ($q) => $q->whereHas('account', fn ($a) => $a->where('cost_center_id', $costCenterId)))
            ->get()
            ->filter(fn (Settlement $s) => $s->account?->category !== null && $s->account->category->type === $type);

        $totals = [];

        foreach ($settlements as $settlement) {
            $name = $settlement->account->category->name;
            $totals[$name] = round(($totals[$name] ?? 0) + (float) $settlement->value, 2);
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
    private function balanceByCostCenter(?string $costCenterId): array
    {
        $rows = $this->reports->byCostCenter()['rows'];

        if ($costCenterId !== null) {
            $rows = array_values(array_filter($rows, fn (array $row) => $row['cost_center_id'] === $costCenterId));
        }

        return $rows;
    }

    /**
     * @param  Collection<int, FinancialAccount>  $accounts
     * @return list<array<string, mixed>>
     */
    private function presentAccounts($accounts): array
    {
        return $accounts
            ->map(fn (FinancialAccount $a) => [
                'id' => $a->uuid,
                'description' => $a->description,
                'counterparty' => $a->counterparty,
                'type' => $a->type->value,
                'cost_center' => $a->costCenter?->name,
                'category' => $a->category?->name,
                'value' => (float) $a->value,
                'remaining_amount' => round((float) $a->value - (float) ($a->settlements_sum_value ?? 0), 2),
                'due_date' => $a->due_date->toDateString(),
            ])
            ->values()
            ->all();
    }
}
