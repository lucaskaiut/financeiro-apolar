<?php

namespace App\Modules\Reconciliation\Services;

use App\Modules\Account\Enums\AccountStatus;
use App\Modules\Account\Models\FinancialAccount;
use App\Modules\Account\Models\Settlement;
use App\Modules\Account\Services\AccountService;
use App\Modules\Reconciliation\Models\BankTransaction;
use App\Modules\Reconciliation\Models\Reconciliation;
use App\Modules\Reconciliation\Support\OfxParser;
use App\Modules\User\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ReconciliationService
{
    public function __construct(
        private readonly OfxParser $parser,
        private readonly AccountService $accounts,
    ) {}

    /**
     * @return array{imported: int, skipped: int}
     */
    public function import(string $costCenterId, string $content): array
    {
        $raw = $this->parser->parse($content);

        $imported = 0;
        $skipped = 0;

        foreach ($raw as $item) {
            $exists = filled($item['transaction_id'])
                && BankTransaction::query()->where('transaction_id', $item['transaction_id'])->exists();

            if ($exists || $item['value'] <= 0 || $item['date'] === null) {
                $skipped++;

                continue;
            }

            BankTransaction::query()->create([
                'cost_center_id' => $costCenterId,
                'date' => $item['date'],
                'value' => $item['value'],
                'type' => $item['type'],
                'description' => $item['description'],
                'transaction_id' => $item['transaction_id'],
                'status' => 'pending',
            ]);

            $imported++;
        }

        return ['imported' => $imported, 'skipped' => $skipped];
    }

    public function paginate(int $perPage = 15, ?string $status = null, ?string $costCenterId = null): LengthAwarePaginator
    {
        return BankTransaction::query()
            ->with(['costCenter:id,uuid,name', 'reconciliation.account:id,uuid,description'])
            ->when(filled($status), fn ($q) => $q->where('status', $status))
            ->when(filled($costCenterId), fn ($q) => $q->where('cost_center_id', $costCenterId))
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->paginate(min(max($perPage, 1), 100));
    }

    /**
     * Executa a conciliação automática sobre transações pendentes.
     *
     * @return array{matched: int, ambiguous: int, not_found: int}
     */
    public function autoReconcile(User $user, ?string $costCenterId = null, ?string $from = null, ?string $to = null): array
    {
        $pending = BankTransaction::query()
            ->where('status', 'pending')
            ->when($costCenterId, fn ($q) => $q->where('cost_center_id', $costCenterId))
            ->orderBy('date')
            ->get();

        $matched = 0;
        $ambiguous = 0;
        $notFound = 0;

        foreach ($pending as $transaction) {
            $candidates = $this->candidates($transaction, $from, $to);

            if ($candidates->count() === 1) {
                $this->link($transaction, $candidates->first(), $user);
                $matched++;
            } elseif ($candidates->count() > 1) {
                $ambiguous++;
            } else {
                $notFound++;
            }
        }

        return ['matched' => $matched, 'ambiguous' => $ambiguous, 'not_found' => $notFound];
    }

    /**
     * Busca lançamentos candidatos à conciliação com uma transação.
     *
     * O match é feito apenas por valor (e centro de custo); a data
     * é usada somente como filtro opcional de período (vencimento).
     *
     * @return Collection<int, FinancialAccount>
     */
    public function candidates(BankTransaction $transaction, ?string $from = null, ?string $to = null): Collection
    {
        $query = FinancialAccount::query()
            ->whereIn('status', [AccountStatus::Open->value, AccountStatus::Partial->value])
            ->where('cost_center_id', $transaction->cost_center_id);

        if (filled($from)) {
            $query->whereDate('due_date', '>=', $from);
        }

        if (filled($to)) {
            $query->whereDate('due_date', '<=', $to);
        }

        return $query
            ->get()
            ->filter(fn (FinancialAccount $account) => abs($account->remaining_amount - (float) $transaction->value) < 0.005);
    }

    /**
     * Vincula manualmente uma transação a uma conta (resolução de ambiguidade).
     */
    public function reconcile(BankTransaction $transaction, FinancialAccount $account, User $user): void
    {
        if ($transaction->status === 'matched') {
            throw new InvalidArgumentException('Transação já conciliada.');
        }

        $this->link($transaction, $account, $user);
    }

    /**
     * Conciliação múltipla (RF017): N transações x N contas com valores equivalentes.
     *
     * @param  list<string>  $transactionIds
     * @param  list<string>  $accountIds
     */
    public function reconcileMany(array $transactionIds, array $accountIds, User $user): void
    {
        $transactions = BankTransaction::query()->whereIn('uuid', $transactionIds)->where('status', 'pending')->get();
        $accounts = FinancialAccount::query()->whereIn('uuid', $accountIds)->whereIn('status', ['open', 'partial'])->get();

        if ($transactions->count() !== count($transactionIds) || $accounts->count() !== count($accountIds)) {
            throw new InvalidArgumentException('Uma ou mais transações ou contas não estão disponíveis.');
        }

        $txTotal = round((float) $transactions->sum('value'), 2);
        $accountTotal = round($accounts->sum('remaining_amount'), 2);

        if (abs($txTotal - $accountTotal) >= 0.01) {
            throw new InvalidArgumentException('Os totais dos dois lados não são equivalentes.');
        }

        if ($transactions->count() === 1) {
            foreach ($accounts as $account) {
                $this->link($transactions->first(), $account, $user);
            }

            return;
        }

        if ($accounts->count() === 1) {
            foreach ($transactions as $transaction) {
                $this->link($transaction, $accounts->first(), $user);
            }

            return;
        }

        foreach ($transactions->values() as $i => $transaction) {
            $this->link($transaction, $accounts->values()->get($i), $user);
        }
    }

    /**
     * Marca uma transação sem correspondente como ignorada (RF019).
     */
    public function ignore(BankTransaction $transaction): void
    {
        $transaction->status = 'ignored';
        $transaction->save();
    }

    /**
     * Cria uma receita ou despesa a partir de uma transação sem correspondente (RF019).
     *
     * Campos não informados são preenchidos automaticamente a partir do extrato.
     *
     * @param  array{type: string, description: string, category_id: string, cost_center_id?: ?string, value?: ?numeric, due_date?: ?string, observation?: ?string}  $data
     */
    public function createFromTransaction(BankTransaction $transaction, array $data, User $user): FinancialAccount
    {
        if ($transaction->status === 'matched') {
            throw new InvalidArgumentException('Transação já conciliada.');
        }

        return DB::transaction(function () use ($transaction, $data, $user): FinancialAccount {
            $account = FinancialAccount::query()->create([
                'type' => $data['type'],
                'description' => $data['description'],
                'cost_center_id' => $data['cost_center_id'] ?? $transaction->cost_center_id,
                'category_id' => $data['category_id'],
                'value' => $data['value'] ?? $transaction->value,
                'due_date' => $data['due_date'] ?? $transaction->date->toDateString(),
                'observation' => $data['observation'] ?? null,
                'status' => AccountStatus::Open,
            ]);

            $this->link($transaction, $account, $user);

            return $account->refresh();
        });
    }

    /**
     * Desfaz a conciliação (RF020): remove vínculo, reabre o lançamento e audita.
     */
    public function undo(BankTransaction $transaction): void
    {
        $reconciliation = Reconciliation::query()
            ->where('bank_transaction_id', $transaction->getKey())
            ->whereNull('reversed_at')
            ->first();

        if ($reconciliation === null) {
            throw new InvalidArgumentException('Transação não possui conciliação ativa.');
        }

        DB::transaction(function () use ($transaction, $reconciliation): void {
            Settlement::query()->where('reconciliation_id', $reconciliation->getKey())->delete();

            $account = $reconciliation->account;

            if ($account !== null) {
                $this->accounts->markUnreconciled($account);
                $this->accounts->recomputeStatus($account);
            }

            $reconciliation->reversed_at = now();
            $reconciliation->save();

            $transaction->status = 'pending';
            $transaction->save();
        });
    }

    private function link(BankTransaction $transaction, FinancialAccount $account, User $user): void
    {
        DB::transaction(function () use ($transaction, $account, $user): void {
            $reconciliation = Reconciliation::query()->create([
                'bank_transaction_id' => $transaction->getKey(),
                'account_id' => $account->getKey(),
                'user_id' => $user->getKey(),
                'created_at' => now(),
            ]);

            Settlement::query()->create([
                'account_id' => $account->getKey(),
                'value' => $account->remaining_amount,
                'settled_at' => $transaction->date->toDateString(),
                'method' => 'reconciliation',
                'user_id' => $user->getKey(),
                'reconciliation_id' => $reconciliation->getKey(),
            ]);

            $account->reconciled_at = now();
            $account->save();

            $this->accounts->recomputeStatus($account);

            $transaction->status = 'matched';
            $transaction->save();
        });
    }
}
