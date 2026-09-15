<?php

namespace App\Modules\Installment\Services;

use App\Modules\Account\Enums\AccountStatus;
use App\Modules\Account\Exceptions\AccountUpdateException;
use App\Modules\Account\Models\FinancialAccount;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\LengthAwarePaginator as Paginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class InstallmentService
{
    /**
     * Lista os parcelamentos (grupos de parcelas) agregando os lançamentos
     * vinculados por installment_group_id.
     */
    public function paginate(int $perPage = 15, ?string $search = null, int $page = 1): LengthAwarePaginator
    {
        $perPage = min(max($perPage, 1), 100);
        $page = max($page, 1);

        $query = FinancialAccount::query()
            ->whereNotNull('installment_group_id')
            ->select([
                'installment_group_id',
                DB::raw('MIN(due_date) as first_due_date'),
                DB::raw('MAX(due_date) as last_due_date'),
                DB::raw('MAX(installment_total) as installment_total'),
                DB::raw('COUNT(*) as installments_count'),
                DB::raw('SUM(value) as total_value'),
                DB::raw("SUM(CASE WHEN status = 'settled' THEN 1 ELSE 0 END) as settled_count"),
                DB::raw("SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled_count"),
            ])
            ->groupBy('installment_group_id');

        if (filled($search)) {
            $query->whereIn('installment_group_id', FinancialAccount::query()
                ->select('installment_group_id')
                ->whereNotNull('installment_group_id')
                ->where(fn ($q) => $q
                    ->where('description', 'like', "%{$search}%")
                    ->orWhere('counterparty', 'like', "%{$search}%")));
        }

        $groups = $query
            ->orderByDesc('first_due_date')
            ->get();

        $total = $groups->count();

        $pageGroups = $groups->forPage($page, $perPage)->values();

        $firsts = FinancialAccount::query()
            ->with(['bankAccount:id,uuid,name', 'category:id,uuid,name,color,type', 'subcategory:id,uuid,name'])
            ->whereIn('installment_group_id', $pageGroups->pluck('installment_group_id')->all())
            ->where('installment_number', 1)
            ->get()
            ->keyBy('installment_group_id');

        $items = $pageGroups
            ->map(fn ($group) => $this->toSummary($group, $firsts->get($group->installment_group_id)))
            ->values();

        return new Paginator($items, $total, $perPage, $page);
    }

    /**
     * Aplica alterações comuns a todas as parcelas do parcelamento.
     *
     * @param  array<string, mixed>  $data
     * @return object|null
     */
    public function update(string $group, array $data, string $scope = 'all'): ?object
    {
        $accounts = FinancialAccount::query()
            ->where('installment_group_id', $group)
            ->orderBy('installment_number')
            ->get();

        if ($accounts->isEmpty()) {
            return null;
        }

        $isCardPurchase = (bool) $accounts->first()->is_card_purchase;
        $today = now()->toDateString();

        $hasValue = array_key_exists('value', $data);
        $value = $hasValue ? (float) $data['value'] : null;
        unset($data['value']);

        if ($isCardPurchase) {
            unset($data['bank_account_id'], $data['type']);
        }

        DB::transaction(function () use ($accounts, $scope, $today, $data, $hasValue, $value): void {
            $targets = $accounts->filter(function (FinancialAccount $account) use ($scope, $today): bool {
                if ($scope !== 'future') {
                    return true;
                }

                return $account->due_date !== null && $account->due_date->toDateString() >= $today;
            });

            foreach ($targets as $account) {
                if ($account->isReconciled() || $account->status === AccountStatus::Cancelled) {
                    continue;
                }

                $account->fill($data);
                $account->save();
            }

            if ($hasValue) {
                $this->applyValue($accounts, $value);
            }
        });

        return $this->find($group);
    }

    /**
     * @param  Collection<int, FinancialAccount>  $accounts
     */
    private function applyValue(Collection $accounts, float $total): void
    {
        foreach ($accounts as $account) {
            if ($account->status !== AccountStatus::Open || $account->isReconciled() || $account->isSplit()) {
                throw new AccountUpdateException(
                    'value',
                    'Não é possível alterar o valor: existem parcelas baixadas, conciliadas, canceladas ou rateadas.',
                );
            }
        }

        $values = $this->distribute($total, $accounts->count());

        foreach ($accounts as $index => $account) {
            $account->value = $values[$index];
            $account->save();
        }
    }

    /**
     * @return list<float>
     */
    private function distribute(float $total, int $quantity): array
    {
        $installmentValue = round($total / $quantity, 2);
        $values = [];
        $accumulated = 0.0;

        for ($i = 0; $i < $quantity; $i++) {
            $value = $i === $quantity - 1
                ? round($total - $accumulated, 2)
                : $installmentValue;

            $accumulated = round($accumulated + $value, 2);
            $values[] = $value;
        }

        return $values;
    }

    /**
     * @return object|null
     */
    public function find(string $group): ?object
    {
        $accounts = FinancialAccount::query()
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
            ->where('installment_group_id', $group)
            ->orderBy('installment_number')
            ->get();

        if ($accounts->isEmpty()) {
            return null;
        }

        $first = $accounts->first();
        $last = $accounts->last();

        return (object) [
            'id' => $group,
            'description' => $first->description,
            'counterparty' => $first->counterparty,
            'type' => $first->type?->value,
            'type_label' => $first->type?->label(),
            'bank_account_id' => $first->bankAccount?->uuid,
            'bank_account' => $first->bankAccount?->name,
            'company_id' => $first->company?->uuid,
            'company' => $first->company?->name,
            'cost_center_id' => $first->costCenter?->uuid,
            'cost_center' => $first->costCenter?->name,
            'category_id' => $first->category?->uuid,
            'category' => $first->category?->name,
            'subcategory_id' => $first->subcategory?->uuid,
            'subcategory' => $first->subcategory?->name,
            'installments_count' => $accounts->count(),
            'installment_total' => (int) $first->installment_total,
            'total_value' => round((float) $accounts->sum('value'), 2),
            'first_due_date' => $first->due_date?->toDateString(),
            'last_due_date' => $last->due_date?->toDateString(),
            'expected_date' => $first->expected_date?->toDateString(),
            'observation' => $first->observation,
            'settled_count' => $accounts->filter(fn (FinancialAccount $a) => $a->isSettled())->count(),
            'cancelled_count' => $accounts->filter(fn (FinancialAccount $a) => $a->status === AccountStatus::Cancelled)->count(),
            'is_card_purchase' => (bool) $first->is_card_purchase,
            'installments' => $accounts,
        ];
    }

    /**
     * @return object
     */
    private function toSummary(object $group, ?FinancialAccount $first): object
    {
        return (object) [
            'id' => $group->installment_group_id,
            'description' => $first?->description,
            'counterparty' => $first?->counterparty,
            'type' => $first?->type?->value,
            'type_label' => $first?->type?->label(),
            'bank_account' => $first?->bankAccount?->name,
            'category' => $first?->category?->name,
            'subcategory' => $first?->subcategory?->name,
            'installments_count' => (int) $group->installments_count,
            'installment_total' => (int) $group->installment_total,
            'total_value' => round((float) $group->total_value, 2),
            'first_due_date' => $group->first_due_date,
            'last_due_date' => $group->last_due_date,
            'settled_count' => (int) $group->settled_count,
            'cancelled_count' => (int) $group->cancelled_count,
            'is_card_purchase' => (bool) $first?->is_card_purchase,
        ];
    }
}
