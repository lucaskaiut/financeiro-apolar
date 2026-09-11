<?php

namespace App\Modules\Reconciliation\Services;

use App\Modules\Account\Enums\AccountStatus;
use App\Modules\Account\Models\FinancialAccount;
use App\Modules\Account\Models\Settlement;
use App\Modules\Account\Services\AccountService;
use App\Modules\Reconciliation\Models\BankTransaction;
use App\Modules\Reconciliation\Models\Reconciliation;
use App\Modules\Reconciliation\Support\OfxParser;
use App\Modules\Reconciliation\Support\StatementSheetParser;
use App\Modules\User\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ReconciliationService
{
    public function __construct(
        private readonly OfxParser $parser,
        private readonly StatementSheetParser $sheetParser,
        private readonly AccountService $accounts,
    ) {}

    /**
     * @return array{imported: int, skipped: int}
     */
    public function import(string $bankAccountId, string $content): array
    {
        return $this->persist($this->parser->parse($content), $bankAccountId);
    }

    /**
     * Importa um extrato em planilha (XLS/XLSX).
     *
     * @return array{imported: int, skipped: int}
     */
    public function importSpreadsheet(string $bankAccountId, string $path): array
    {
        return $this->persist($this->sheetParser->parse($path), $bankAccountId);
    }

    /**
     * @param  list<array{type: string, date: ?string, value: float, description: ?string, transaction_id: ?string}>  $items
     * @return array{imported: int, skipped: int}
     */
    private function persist(array $items, string $bankAccountId): array
    {
        $imported = 0;
        $skipped = 0;

        foreach ($items as $item) {
            $exists = filled($item['transaction_id'])
                && BankTransaction::query()->where('transaction_id', $item['transaction_id'])->exists();

            if ($exists || $item['value'] <= 0 || $item['date'] === null) {
                $skipped++;

                continue;
            }

            BankTransaction::query()->create([
                'bank_account_id' => $bankAccountId,
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

    public function paginate(int $perPage = 15, ?string $status = null, ?string $bankAccountId = null): LengthAwarePaginator
    {
        return BankTransaction::query()
            ->with([
                'bankAccount:id,uuid,name',
                'reconciliations' => fn ($q) => $q->whereNull('reversed_at')->with('account:id,uuid,description'),
            ])
            ->when(filled($status), fn ($q) => $q->where('status', $status))
            ->when(filled($bankAccountId), fn ($q) => $q->where('bank_account_id', $bankAccountId))
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->paginate(min(max($perPage, 1), 100));
    }

    /**
     * Executa a conciliação automática sobre transações pendentes.
     *
     * @return array{matched: int, ambiguous: int, not_found: int}
     */
    public function autoReconcile(User $user, ?string $bankAccountId = null, ?string $from = null, ?string $to = null): array
    {
        $pending = BankTransaction::query()
            ->where('status', 'pending')
            ->when($bankAccountId, fn ($q) => $q->where('bank_account_id', $bankAccountId))
            ->orderBy('date')
            ->get();

        $matched = 0;
        $ambiguous = 0;
        $notFound = 0;

        foreach ($pending as $transaction) {
            $candidates = $this->candidates($transaction, $from, $to, exactValue: true);

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
     * Identifica, para cada transação pendente, os lançamentos que seriam vinculados
     * pela conciliação automática (valor exato), sem efetivar a conciliação.
     *
     * @return array<string, Collection<int, FinancialAccount>>
     */
    public function identify(?string $bankAccountId = null, ?string $from = null, ?string $to = null): array
    {
        $pending = BankTransaction::query()
            ->where('status', 'pending')
            ->when($bankAccountId, fn ($q) => $q->where('bank_account_id', $bankAccountId))
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->get();

        $result = [];

        foreach ($pending as $transaction) {
            $result[$transaction->uuid] = $this->candidates($transaction, $from, $to, exactValue: true);
        }

        return $result;
    }

    /**
     * Busca lançamentos candidatos à conciliação com uma transação.
     *
     * Com $exactValue=true (auto), exige igualdade de valor restante.
     * Com $exactValue=false (manual), lista todas as contas abertas/parciais.
     *
     * @return Collection<int, FinancialAccount>
     */
    public function candidates(
        BankTransaction $transaction,
        ?string $from = null,
        ?string $to = null,
        bool $exactValue = true,
    ): Collection {
        $query = FinancialAccount::query()
            ->with(['bankAccount:id,uuid,name'])
            ->withSum('settlements', 'value')
            ->whereIn('status', [AccountStatus::Open->value, AccountStatus::Partial->value])
            // Compras do cartão não entram na conciliação bancária — só a fatura.
            ->where('is_card_purchase', false);

        if (filled($from)) {
            $query->whereDate('due_date', '>=', $from);
        }

        if (filled($to)) {
            $query->whereDate('due_date', '<=', $to);
        }

        $accounts = $query
            ->orderBy('due_date')
            ->orderBy('id')
            ->get()
            ->filter(fn (FinancialAccount $account) => $account->remaining_amount > 0.004)
            ->values();

        if ($exactValue) {
            $accounts = $accounts
                ->filter(fn (FinancialAccount $account) => abs($account->remaining_amount - (float) $transaction->value) < 0.005)
                ->values();
        }

        return $accounts
            ->sortBy([
                fn (FinancialAccount $account) => $account->bank_account_id === $transaction->bank_account_id ? 0 : 1,
                fn (FinancialAccount $account) => abs($account->remaining_amount - (float) $transaction->value) < 0.005 ? 0 : 1,
                fn (FinancialAccount $account) => $account->due_date?->toDateString() ?? '',
            ])
            ->values();
    }

    /**
     * Vincula manualmente uma transação a uma conta (resolução de ambiguidade).
     */
    public function reconcile(BankTransaction $transaction, FinancialAccount $account, User $user): void
    {
        if ($transaction->status === 'matched') {
            throw new InvalidArgumentException('Transação já conciliada.');
        }

        if (abs($account->remaining_amount - (float) $transaction->value) >= 0.01) {
            throw new InvalidArgumentException('O valor do lançamento deve ser igual ao valor da transação. Use a conciliação múltipla para ratear.');
        }

        $this->link($transaction, $account, $user);
    }

    /**
     * Conciliação múltipla (RF017): N transações x N contas com valores equivalentes.
     * Suporta 1 extrato → N contas quando a soma dos saldos for igual ao valor do extrato.
     *
     * @param  list<string>  $transactionIds
     * @param  list<string>  $accountIds
     */
    public function reconcileMany(array $transactionIds, array $accountIds, User $user): void
    {
        DB::transaction(function () use ($transactionIds, $accountIds, $user): void {
            $transactions = BankTransaction::query()->whereIn('uuid', $transactionIds)->where('status', 'pending')->get();
            $accounts = FinancialAccount::query()
                ->withSum('settlements', 'value')
                ->whereIn('uuid', $accountIds)
                ->whereIn('status', [AccountStatus::Open->value, AccountStatus::Partial->value])
                ->where('is_card_purchase', false)
                ->get();

            if ($transactions->count() !== count($transactionIds) || $accounts->count() !== count($accountIds)) {
                throw new InvalidArgumentException('Uma ou mais transações ou contas não estão disponíveis.');
            }

            $txTotal = round((float) $transactions->sum('value'), 2);
            $accountTotal = round($accounts->sum(fn (FinancialAccount $account) => $account->remaining_amount), 2);

            if (abs($txTotal - $accountTotal) >= 0.01) {
                throw new InvalidArgumentException('Os totais dos dois lados não são equivalentes.');
            }

            if ($transactions->count() === 1) {
                $transaction = $transactions->first();

                foreach ($accounts as $account) {
                    $this->link($transaction, $account, $user, $account->remaining_amount, markMatched: false);
                }

                $transaction->status = 'matched';
                $transaction->save();

                return;
            }

            if ($accounts->count() === 1) {
                $account = $accounts->first();

                foreach ($transactions as $transaction) {
                    $this->link($transaction, $account, $user, (float) $transaction->value);
                }

                return;
            }

            if ($transactions->count() !== $accounts->count()) {
                throw new InvalidArgumentException('Para conciliar vários extratos com várias contas, as quantidades devem ser iguais.');
            }

            $availableAccounts = $accounts->values()->all();

            foreach ($transactions->values() as $transaction) {
                $txValue = round((float) $transaction->value, 2);
                $matchIndex = null;

                foreach ($availableAccounts as $index => $account) {
                    if (abs($account->remaining_amount - $txValue) < 0.01) {
                        $matchIndex = $index;
                        break;
                    }
                }

                if ($matchIndex === null) {
                    throw new InvalidArgumentException(
                        'Não foi possível emparelhar extratos e contas com o mesmo valor. Use conciliação 1→N ou N→1.',
                    );
                }

                $account = $availableAccounts[$matchIndex];
                unset($availableAccounts[$matchIndex]);
                $availableAccounts = array_values($availableAccounts);

                $this->link($transaction, $account, $user, $account->remaining_amount);
            }
        });
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
     * Cria uma receita ou despesa a partir de uma transação (RF019).
     *
     * Quando `account_ids` é informado, cria o lançamento complementar e concilia
     * o extrato com as contas selecionadas + a nova, exigindo que a soma feche.
     *
     * @param  array{type: string, description: string, category_id: string, bank_account_id?: ?string, cost_center_id?: ?string, value?: ?numeric, due_date?: ?string, observation?: ?string, account_ids?: list<string>}  $data
     */
    public function createFromTransaction(BankTransaction $transaction, array $data, User $user): FinancialAccount
    {
        if ($transaction->status === 'matched') {
            throw new InvalidArgumentException('Transação já conciliada.');
        }

        $accountIds = array_values(array_unique($data['account_ids'] ?? []));
        unset($data['account_ids']);

        return DB::transaction(function () use ($transaction, $data, $user, $accountIds): FinancialAccount {
            $value = round((float) ($data['value'] ?? $transaction->value), 2);
            $costCenterId = $data['cost_center_id'] ?? null;

            if ($accountIds === []) {
                if (abs($value - (float) $transaction->value) >= 0.01) {
                    throw new InvalidArgumentException('O valor do lançamento deve ser igual ao valor da transação do extrato.');
                }

                $account = FinancialAccount::query()->create([
                    'type' => $data['type'],
                    'description' => $data['description'],
                    'bank_account_id' => $data['bank_account_id'] ?? $transaction->bank_account_id,
                    'cost_center_id' => $costCenterId,
                    'category_id' => $data['category_id'],
                    'value' => $value,
                    'due_date' => $data['due_date'] ?? $transaction->date->toDateString(),
                    'observation' => $data['observation'] ?? null,
                    'status' => AccountStatus::Open,
                ]);

                $this->link($transaction, $account, $user);

                return $account->refresh();
            }

            $selectedAccounts = FinancialAccount::query()
                ->withSum('settlements', 'value')
                ->whereIn('uuid', $accountIds)
                ->whereIn('status', [AccountStatus::Open->value, AccountStatus::Partial->value])
                ->where('is_card_purchase', false)
                ->get();

            if ($selectedAccounts->count() !== count($accountIds)) {
                throw new InvalidArgumentException('Uma ou mais contas selecionadas não estão disponíveis.');
            }

            $selectedTotal = round($selectedAccounts->sum(fn (FinancialAccount $account) => $account->remaining_amount), 2);
            $combinedTotal = round($selectedTotal + $value, 2);

            if (abs($combinedTotal - (float) $transaction->value) >= 0.01) {
                throw new InvalidArgumentException(
                    sprintf(
                        'A soma das contas selecionadas (R$ %s) com o novo lançamento (R$ %s) deve ser igual ao valor do extrato (R$ %s).',
                        number_format($selectedTotal, 2, ',', '.'),
                        number_format($value, 2, ',', '.'),
                        number_format((float) $transaction->value, 2, ',', '.'),
                    ),
                );
            }

            $account = FinancialAccount::query()->create([
                'type' => $data['type'],
                'description' => $data['description'],
                'bank_account_id' => $transaction->bank_account_id,
                'cost_center_id' => $costCenterId,
                'category_id' => $data['category_id'],
                'value' => $value,
                'due_date' => $data['due_date'] ?? $transaction->date->toDateString(),
                'observation' => $data['observation'] ?? null,
                'status' => AccountStatus::Open,
            ]);

            $this->reconcileMany(
                [$transaction->uuid],
                [...$accountIds, $account->uuid],
                $user,
            );

            return $account->refresh()->load(['bankAccount:id,uuid,name', 'costCenter:id,uuid,name', 'category:id,uuid,name']);
        });
    }

    /**
     * Desfaz a conciliação (RF020): remove todos os vínculos ativos, reabre os lançamentos e audita.
     */
    public function undo(BankTransaction $transaction): void
    {
        $reversed = $this->accounts->reverseBankTransaction($transaction);

        if ($reversed === 0) {
            throw new InvalidArgumentException('Transação não possui conciliação ativa.');
        }
    }

    private function link(
        BankTransaction $transaction,
        FinancialAccount $account,
        User $user,
        ?float $amount = null,
        bool $markMatched = true,
    ): void {
        DB::transaction(function () use ($transaction, $account, $user, $amount, $markMatched): void {
            $account->refresh();
            $account->loadSum('settlements', 'value');

            $settlementValue = round($amount ?? $account->remaining_amount, 2);

            if ($settlementValue <= 0) {
                throw new InvalidArgumentException('Não há saldo restante para conciliar neste lançamento.');
            }

            if ($settlementValue - $account->remaining_amount > 0.009) {
                throw new InvalidArgumentException('O valor a conciliar excede o saldo restante do lançamento.');
            }

            $reconciliation = Reconciliation::query()->create([
                'bank_transaction_id' => $transaction->getKey(),
                'account_id' => $account->getKey(),
                'user_id' => $user->getKey(),
                'created_at' => now(),
            ]);

            Settlement::query()->create([
                'account_id' => $account->getKey(),
                'value' => $settlementValue,
                'settled_at' => $transaction->date->toDateString(),
                'method' => 'reconciliation',
                'user_id' => $user->getKey(),
                'reconciliation_id' => $reconciliation->getKey(),
            ]);

            unset($account->settlements_sum_value);
            $account->unsetRelation('settlements');

            if (
                filled($transaction->bank_account_id)
                && $account->bank_account_id !== $transaction->bank_account_id
            ) {
                $account->bank_account_id = $transaction->bank_account_id;
            }

            $account->reconciled_at = now();
            $account->save();

            $this->accounts->recomputeStatus($account);
            $this->accounts->syncCardInvoicePaymentState($account);

            if ($markMatched) {
                $transaction->status = 'matched';
                $transaction->save();
            }
        });
    }
}
